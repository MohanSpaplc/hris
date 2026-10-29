<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Country;
use App\Models\PracticeArea;
use App\Models\Skill;
use Illuminate\Support\Str;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

/**
 * Reads a job description file (PDF, DOCX, XLSX, TXT) and suggests job form values.
 * Rule-based: labelled lines ("Experience: 5-8 years"), keywords ("hybrid", "C2H")
 * and names already in the masters (skills, countries, clients, practice areas).
 */
class JdParser
{
    /** Largest XML part we will read from a DOCX/XLSX (guards against zip bombs). */
    private const MAX_XML_BYTES = 15 * 1024 * 1024;

    private const MAX_TEXT_CHARS = 60000;

    protected const BULLET = '/^\s*(?:[•●▪◦·\-\*–]|o\s|\d{1,2}[.)])\s*/u';

    /** @var list<string> */
    protected array $lines = [];

    protected string $text = '';

    /**
     * Plain text of the file.
     */
    public function extractText(string $path, string $extension): string
    {
        $text = match (strtolower($extension)) {
            'pdf' => (new PdfParser)->parseFile($path)->getText(),
            'docx' => $this->docxText($path),
            'xlsx' => $this->xlsxText($path),
            'txt' => (string) file_get_contents($path),
            default => throw new RuntimeException("Unsupported file type: {$extension}"),
        };

        // Normalise line endings / spaces and drop invalid UTF-8.
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return Str::limit(trim($text), self::MAX_TEXT_CHARS, '');
    }

    /**
     * Suggested values for the job form, plus the description as safe HTML.
     *
     * @return array{job_description: string|null, fields: array<string, mixed>}
     */
    public function parse(string $text): array
    {
        $this->text = $text;
        $this->lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($line) => $line !== ''));

        $fields = array_filter([
            'job_title' => $this->jobTitle(),
            ...$this->experience(),
            'work_location' => $this->labelled(['work location', 'job location', 'location', 'base location', 'place of work']),
            'work_mode' => $this->workMode(),
            'employment_type' => $this->employmentType(),
            'no_of_positions' => $this->positions(),
            'contract_duration' => $this->labelled(['contract duration', 'duration', 'tenure', 'contract period']),
            ...$this->budget(),
            ...$this->skills(),
            'country_id' => $this->matchCountry(),
            'client_id' => $this->matchByName(Client::query()->active()->get(['id', 'name'])),
            'practice_area_id' => $this->matchPracticeArea(),
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');

        return ['job_description' => $this->toHtml(), 'fields' => $fields];
    }

    // ------------------------------------------------------------------ text extraction

    private function docxText(string $path): string
    {
        $xml = $this->zipEntry($path, 'word/document.xml');
        // List paragraphs get a bullet (numPr sits before the text); paragraph/line breaks become new lines.
        $xml = preg_replace('/<w:numPr>.*?<\/w:numPr>/s', "\u{2022} ", $xml);
        $xml = str_replace(['</w:p>', '<w:br/>', '<w:cr/>', '<w:tab/>', '</w:tc>'], ["\n", "\n", "\n", "\t", "\t"], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function xlsxText(string $path): string
    {
        $shared = [];
        $sharedXml = $this->zipEntry($path, 'xl/sharedStrings.xml', required: false);
        if ($sharedXml !== '') {
            $doc = simplexml_load_string($sharedXml, options: LIBXML_NONET);
            foreach ($doc?->si ?? [] as $item) {
                $shared[] = trim(strip_tags($item->asXML()));
            }
        }

        $zip = $this->openZip($path);
        $lines = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                continue;
            }
            $sheet = simplexml_load_string($this->zipEntry($path, $name), options: LIBXML_NONET);
            foreach ($sheet?->sheetData->row ?? [] as $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    $value = match ((string) $cell['t']) {
                        's' => $shared[(int) $cell->v] ?? '',
                        'inlineStr' => trim(strip_tags($cell->is->asXML())),
                        default => (string) $cell->v,
                    };
                    if (trim($value) !== '') {
                        $cells[] = trim($value);
                    }
                }
                // Two cells read as "Label: Value" so labelled fields are found.
                if ($cells) {
                    $lines[] = count($cells) === 2 ? "{$cells[0]}: {$cells[1]}" : implode(' | ', $cells);
                }
            }
        }
        $zip->close();

        return implode("\n", $lines);
    }

    private function openZip(string $path): ZipArchive
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The file is not a valid Office document.');
        }

        return $zip;
    }

    private function zipEntry(string $path, string $entry, bool $required = true): string
    {
        $zip = $this->openZip($path);
        $stat = $zip->statName($entry);

        if ($stat === false) {
            $zip->close();
            if ($required) {
                throw new RuntimeException('The document has no readable text.');
            }

            return '';
        }
        if ($stat['size'] > self::MAX_XML_BYTES) {
            $zip->close();
            throw new RuntimeException('The document is too large to read.');
        }

        $content = (string) $zip->getFromName($entry);
        $zip->close();

        return $content;
    }

    // ------------------------------------------------------------------ field matching

    /**
     * Value after a label on the same line: "Location: Bengaluru" / "Location - Bengaluru".
     *
     * @param  list<string>  $labels
     */
    protected function labelled(array $labels): ?string
    {
        $pattern = '/^\s*(?:'.implode('|', array_map(fn ($l) => preg_quote($l, '/'), $labels)).')\s*(?:\(.*?\))?\s*[:\-–]\s*(.+)$/iu';

        foreach ($this->lines as $line) {
            if (preg_match($pattern, $line, $match)) {
                return Str::limit(trim($match[1], " \t.;"), 200, '');
            }
        }

        return null;
    }

    /**
     * Lines of a section: text after "Label:" plus the following bullet/short lines.
     *
     * @param  list<string>  $labels
     * @return list<string>
     */
    protected function section(array $labels): array
    {
        $heading = '/^\s*(?:'.implode('|', array_map(fn ($l) => preg_quote($l, '/'), $labels)).')\b\s*(?:\(.*?\))?\s*[:\-–]?\s*(.*)$/iu';

        foreach ($this->lines as $index => $line) {
            if (! preg_match($heading, $line, $match) || mb_strlen($line) > 300) {
                continue;
            }

            // After the heading: bullet lines, or one plain line when the heading had nothing after it.
            $collected = $match[1] !== '' ? [$match[1]] : [];
            foreach (array_slice($this->lines, $index + 1, 15) as $next) {
                $isBullet = (bool) preg_match(self::BULLET, $next);
                $plainLineAllowed = $collected === [] && mb_strlen($next) <= 80 && ! preg_match('/^[^:]{2,40}:/u', $next);
                if (! $isBullet && ! $plainLineAllowed) {
                    break;
                }
                $collected[] = $next;
                if (! $isBullet) {
                    break;
                }
            }

            return $collected;
        }

        return [];
    }

    private function jobTitle(): ?string
    {
        $title = $this->labelled(['job title', 'position title', 'job role', 'role title', 'designation', 'position', 'role', 'title', 'opening']);

        if (! $title) {
            // First short line that is not a generic heading.
            foreach (array_slice($this->lines, 0, 5) as $line) {
                $clean = trim(preg_replace(self::BULLET, '', $line));
                if (mb_strlen($clean) >= 3 && mb_strlen($clean) <= 80 && ! str_contains($clean, ':')
                    && ! preg_match('/^(job description|jd|about|company|overview)$/i', $clean)) {
                    $title = $clean;
                    break;
                }
            }
        }

        return $title ? Str::limit($title, 200, '') : null;
    }

    /**
     * @return array{total_experience?: float, relevant_experience?: float}
     */
    private function experience(): array
    {
        $result = [];
        $source = $this->labelled(['total experience', 'experience required', 'experience', 'exp', 'work experience']) ?? $this->text;

        if (preg_match('/(\d{1,2}(?:\.\d)?)\s*\+?\s*(?:-|–|to)\s*(\d{1,2}(?:\.\d)?)\s*\+?\s*(?:years?|yrs?)/iu', $source, $m)
            || preg_match('/(\d{1,2}(?:\.\d)?)\s*\+?\s*(?:years?|yrs?)/iu', $source, $m)) {
            $result['total_experience'] = min(60.0, (float) $m[1]); // minimum required
        }

        // "Relevant experience: 4 years" can also share a line with other values.
        if (preg_match('/relevant\s+(?:experience|exp)\.?\s*(?:of\s*)?[:\-–]?\s*(\d{1,2}(?:\.\d)?)/iu', $this->text, $m)) {
            $result['relevant_experience'] = min((float) $m[1], $result['total_experience'] ?? 60.0);
        }

        return $result;
    }

    private function workMode(): ?string
    {
        $source = mb_strtolower($this->labelled(['work mode', 'mode of work', 'working mode', 'work model']) ?? $this->text);

        return match (true) {
            str_contains($source, 'hybrid') => 'hybrid',
            (bool) preg_match('/\b(remote|work from home|wfh)\b/', $source) => 'remote',
            (bool) preg_match('/\b(on[- ]?site|work from office|wfo|in[- ]office)\b/', $source) => 'onsite',
            default => null,
        };
    }

    private function employmentType(): ?string
    {
        $source = mb_strtolower($this->labelled(['employment type', 'job type', 'engagement type', 'type of employment', 'position type']) ?? $this->text);

        return match (true) {
            (bool) preg_match('/\b(c2h|contract[- ]to[- ]hire)\b/', $source) => 'c2h',
            (bool) preg_match('/\bintern(ship)?\b/', $source) => 'intern',
            (bool) preg_match('/\b(full[- ]?time|permanent)\b/', $source) => 'full_time',
            (bool) preg_match('/\bcontract(ual)?\b/', $source) => 'contract',
            default => null,
        };
    }

    private function positions(): ?int
    {
        $value = $this->labelled(['no of positions', 'no. of positions', 'number of positions', 'positions', 'openings', 'vacancies', 'headcount', 'no of openings']);

        return $value && preg_match('/\d{1,4}/', $value, $m) && (int) $m[0] >= 1 ? min(1000, (int) $m[0]) : null;
    }

    /**
     * @return array{budget_min?: float, budget_max?: float}
     */
    private function budget(): array
    {
        $value = $this->labelled(['budget', 'ctc', 'salary', 'compensation', 'package', 'pay rate', 'rate']);
        if (! $value || ! preg_match_all('/\d[\d,]*(?:\.\d+)?/', $value, $numbers)) {
            return [];
        }

        $multiplier = match (true) {
            (bool) preg_match('/\b(lpa|lakhs?|lacs?|l)\b/i', $value) => 100000,
            (bool) preg_match('/\b(cr|crores?)\b/i', $value) => 10000000,
            (bool) preg_match('/\d\s*k\b/i', $value) => 1000,
            default => 1,
        };
        $amounts = array_map(fn ($n) => (float) str_replace(',', '', $n) * $multiplier, array_slice($numbers[0], 0, 2));

        return ['budget_min' => min($amounts), 'budget_max' => max($amounts)];
    }

    /**
     * @return array{primary_skills?: list<string>, secondary_skills?: list<string>}
     */
    private function skills(): array
    {
        $primary = $this->splitSkills($this->section(['primary skills', 'mandatory skills', 'must have skills', 'must have', 'must-have', 'key skills', 'required skills', 'technical skills', 'skills required', 'skill set', 'skills']));
        $secondary = $this->splitSkills($this->section(['secondary skills', 'good to have', 'nice to have', 'preferred skills', 'desirable skills', 'desired skills', 'additional skills']));

        // Known skills mentioned anywhere fill the primary list when no skills section was found.
        if (! $primary) {
            $primary = Skill::query()->active()->pluck('name')
                ->filter(fn ($name) => preg_match('/(?<![\w.#+])'.preg_quote($name, '/').'(?![\w#+])/iu', $this->text))
                ->values()->all();
        }

        $secondary = array_values(array_filter($secondary, fn ($skill) => ! in_array(mb_strtolower($skill), array_map('mb_strtolower', $primary), true)));

        return array_filter(['primary_skills' => array_slice($primary, 0, 30), 'secondary_skills' => array_slice($secondary, 0, 30)]);
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    protected function splitSkills(array $lines): array
    {
        $skills = [];
        foreach ($lines as $line) {
            foreach (preg_split('/[,;|\/•]|\band\b/iu', preg_replace(self::BULLET, '', $line)) as $part) {
                $skill = trim($part, " \t.:-–()");
                // Keep short names only; long phrases are sentences, not skills.
                if (mb_strlen($skill) >= 1 && mb_strlen($skill) <= 40 && str_word_count($skill) <= 4 && ! preg_match('/^\d+\+?\s*(years?|yrs?)?$/i', $skill)) {
                    $skills[mb_strtolower($skill)] = $skill;
                }
            }
        }

        return array_values($skills);
    }

    private function matchCountry(): ?int
    {
        $location = $this->labelled(['work location', 'job location', 'location', 'country']) ?? '';
        $countries = Country::query()->active()->get(['id', 'name', 'code', 'iso3_code']);

        foreach ([$location, $this->text] as $source) {
            foreach ($countries as $country) {
                if (preg_match('/\b'.preg_quote($country->name, '/').'\b/iu', $source)
                    || ($country->iso3_code && preg_match('/\b'.preg_quote($country->iso3_code, '/').'\b/u', $source))) {
                    return $country->id;
                }
            }
        }

        return null;
    }

    private function matchPracticeArea(): ?int
    {
        foreach (PracticeArea::query()->active()->get(['id', 'name', 'code']) as $area) {
            if (preg_match('/\b'.preg_quote($area->name, '/').'\b/iu', $this->text)
                // Codes of 3+ letters match in any case (JAVA ~ Java); short codes like QA must match exactly.
                || preg_match('/(?<![\w.])'.preg_quote($area->code, '/').'(?!\w)/u'.(mb_strlen($area->code) >= 3 ? 'i' : ''), $this->text)) {
                return $area->id;
            }
        }

        return null;
    }

    /**
     * @param  iterable<object{id: int, name: string}>  $records
     */
    private function matchByName(iterable $records): ?int
    {
        foreach ($records as $record) {
            if (mb_strlen($record->name) >= 3 && mb_stripos($this->text, $record->name) !== false) {
                return $record->id;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ description

    /**
     * The text as simple, escaped HTML: headings, paragraphs and bullet lists.
     */
    private function toHtml(): ?string
    {
        $html = [];
        $listItems = [];
        $flushList = function () use (&$html, &$listItems) {
            if ($listItems) {
                $html[] = '<ul>'.implode('', array_map(fn ($item) => '<li>'.e($item).'</li>', $listItems)).'</ul>';
                $listItems = [];
            }
        };

        foreach ($this->lines as $line) {
            if (preg_match(self::BULLET, $line) && ! preg_match('/^\s*\d{1,2}[.)]\d/', $line)) {
                $listItems[] = trim(preg_replace(self::BULLET, '', $line));

                continue;
            }

            $flushList();
            $isHeading = mb_strlen($line) <= 60 && (str_ends_with($line, ':') || (mb_strtoupper($line) === $line && preg_match('/\p{L}/u', $line)));
            $html[] = $isHeading ? '<h3>'.e(rtrim($line, ':')).'</h3>' : '<p>'.e($line).'</p>';
        }
        $flushList();

        return $html ? implode("\n", $html) : null;
    }
}
