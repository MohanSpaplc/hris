<?php

namespace App\Support;

use App\Models\Skill;
use Illuminate\Support\Str;

/**
 * Reads a resume (text from JdParser::extractText) and suggests candidate form values.
 * Rule-based: email / phone patterns, labelled lines ("Notice Period: 30 days"),
 * section headings ("Skills") and skill names already in the skill master.
 */
class ResumeParser extends JdParser
{
    private const DEGREES = [
        'Ph.D' => 'ph\.?\s?d|doctorate',
        'M.Tech' => 'm\.?\s?tech',
        'M.E' => 'm\.\s?e\.?(?=\s|,|$)',
        'MBA' => 'm\.?b\.?a',
        'MCA' => 'm\.?c\.?a',
        'M.Sc' => 'm\.?\s?sc',
        'M.Com' => 'm\.?\s?com',
        'B.Tech' => 'b\.?\s?tech',
        'B.E' => 'b\.\s?e\.?(?=\s|,|$)|bachelor of engineering',
        'BCA' => 'b\.?c\.?a',
        'B.Sc' => 'b\.?\s?sc',
        'B.Com' => 'b\.?\s?com',
        'Diploma' => 'diploma',
        'Master\'s' => 'master(?:\'s)? of|masters? degree',
        'Bachelor\'s' => 'bachelor(?:\'s)? of|bachelors? degree',
        'Intermediate (12th)' => 'intermediate|12th|hsc|higher secondary|class xii',
        'SSC (10th)' => 'ssc|10th|class x\b|matriculation',
    ];

    /**
     * Suggested candidate form values. Only fields that were found are returned.
     *
     * @return array{fields: array<string, mixed>}
     */
    public function parse(string $text): array
    {
        $this->text = $text;
        $this->lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($line) => $line !== ''));

        $educations = $this->educations();

        $fields = array_filter([
            ...$this->name(),
            'email' => $this->email(),
            'phone' => $this->phone(),
            'gender' => $this->gender(),
            'current_location' => $this->short($this->labelled(['current location', 'location', 'city', 'current city', 'based in'])),
            'current_designation' => $this->short($this->labelled(['current designation', 'designation', 'current role', 'current position', 'job title', 'role', 'title'])),
            'current_company' => $this->short($this->labelled(['current company', 'current organization', 'current organisation', 'current employer', 'organization', 'organisation', 'company', 'employer'])),
            'preferred_location' => $this->short($this->labelled(['preferred location', 'preferred locations', 'preferred city', 'location preference'])),
            ...$this->experience(),
            'current_ctc' => $this->money($this->labelled(['current ctc', 'current salary', 'current package', 'ctc', 'present ctc'])),
            'expected_ctc' => $this->money($this->labelled(['expected ctc', 'expected salary', 'expected package'])),
            'notice_period_days' => $this->noticePeriod(),
            ...$this->resumeSkills(),
            'educations' => $educations,
            'education_details' => $this->highestQualification($educations),
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');

        return ['fields' => $fields];
    }

    /**
     * "Name: Anita Sharma", else the first line that looks like a person's name.
     *
     * @return array{first_name?: string, last_name?: string}
     */
    private function name(): array
    {
        $name = $this->labelled(['name', 'full name', 'candidate name']);

        if (! $name) {
            foreach (array_slice($this->lines, 0, 6) as $line) {
                $clean = trim(preg_replace('/^(resume|curriculum vitae|cv)\s*[:\-–]?\s*/i', '', $line));
                if (preg_match('/^[\p{L}][\p{L}.\' ]{1,60}$/u', $clean) && str_word_count($clean) <= 4
                    && ! preg_match('/^(resume|curriculum vitae|cv|profile|summary|objective|contact)$/i', $clean)) {
                    $name = $clean;
                    break;
                }
            }
        }

        if (! $name || ! preg_match('/^[\p{L}][\p{L}.\' ]{1,60}$/u', $name)) {
            return [];
        }

        // "ANITA SHARMA" → "Anita Sharma"; keep mixed case as written.
        if (mb_strtoupper($name) === $name) {
            $name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
        }
        $parts = preg_split('/\s+/', trim($name));

        return array_filter(['first_name' => array_shift($parts), 'last_name' => implode(' ', $parts) ?: null]);
    }

    private function email(): ?string
    {
        return preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $this->text, $match) ? mb_strtolower($match[0]) : null;
    }

    /**
     * First phone-like number with 10–13 digits (dates and years have fewer).
     */
    private function phone(): ?string
    {
        preg_match_all('/\+?\(?\d[\d\s().\-]{8,18}\d/', $this->text, $matches);
        foreach ($matches[0] as $candidate) {
            $digits = preg_replace('/\D/', '', $candidate);
            if (strlen($digits) >= 10 && strlen($digits) <= 13) {
                return Str::limit(trim(preg_replace('/[^\d+ ()\-]/', '', $candidate)), 30, '');
            }
        }

        return null;
    }

    private function gender(): ?string
    {
        $value = mb_strtolower((string) $this->labelled(['gender', 'sex']));

        return match (true) {
            str_starts_with($value, 'f') => 'female',
            str_starts_with($value, 'm') => 'male',
            $value !== '' => 'other',
            default => null,
        };
    }

    /**
     * @return array{total_experience?: float, relevant_experience?: float}
     */
    private function experience(): array
    {
        $result = [];
        $years = '(\d{1,2}(?:\.\d{1,2})?)\s*\+?\s*(?:years?|yrs?)(?:\s*(?:and\s*)?(\d{1,2})\s*(?:months?|mos?))?';
        $toYears = fn (array $m) => round(min(60, (float) $m[1] + (isset($m[2]) && $m[2] !== '' ? (int) $m[2] / 12 : 0)), 1);

        $labelled = $this->labelled(['total experience', 'total exp', 'experience', 'work experience', 'overall experience']);
        if (($labelled && preg_match("/{$years}/iu", $labelled, $m))
            || preg_match("/{$years}\s*(?:of\s*)?(?:total\s*|overall\s*|professional\s*|industry\s*|it\s*)?(?:work\s*)?(?:experience|exp)/iu", $this->text, $m)) {
            $result['total_experience'] = $toYears($m);
        }

        $relevant = $this->labelled(['relevant experience', 'relevant exp']);
        if ($relevant && preg_match("/{$years}/iu", $relevant, $m)) {
            $result['relevant_experience'] = min($toYears($m), $result['total_experience'] ?? 60.0);
        }

        return $result;
    }

    /**
     * "12 LPA" / "12.5 lakhs" / "1,200,000" / "80k" → amount.
     */
    private function money(?string $value): ?float
    {
        if (! $value || ! preg_match('/(\d[\d,]*(?:\.\d+)?)\s*(lpa|lakhs?|lacs?|l\b|k\b|cr|crores?)?/iu', $value, $m)) {
            return null;
        }

        $amount = (float) str_replace(',', '', $m[1]);
        $unit = mb_strtolower($m[2] ?? '');

        return match (true) {
            in_array($unit, ['lpa', 'lakh', 'lakhs', 'lac', 'lacs', 'l'], true) => $amount * 100000,
            $unit === 'k' => $amount * 1000,
            str_starts_with($unit, 'cr') => $amount * 10000000,
            default => $amount,
        };
    }

    private function noticePeriod(): ?int
    {
        $value = mb_strtolower((string) $this->labelled(['notice period', 'notice']));

        return match (true) {
            $value === '' => null,
            str_contains($value, 'immediate') || str_contains($value, 'serving') && ! preg_match('/\d/', $value) => 0,
            (bool) preg_match('/(\d{1,3})\s*(?:days?)/', $value, $m) => min(365, (int) $m[1]),
            (bool) preg_match('/(\d{1,2})\s*(?:weeks?)/', $value, $m) => min(365, (int) $m[1] * 7),
            (bool) preg_match('/(\d{1,2})\s*(?:months?)/', $value, $m) => min(365, (int) $m[1] * 30),
            default => null,
        };
    }

    /**
     * @return array{primary_skills?: list<string>, secondary_skills?: list<string>}
     */
    private function resumeSkills(): array
    {
        $primary = $this->splitSkills($this->section(['primary skills', 'key skills', 'technical skills', 'core skills', 'skill set', 'skillset', 'skills', 'technologies', 'core competencies']));
        $secondary = $this->splitSkills($this->section(['secondary skills', 'other skills', 'additional skills', 'familiar with', 'tools']));

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
     * Lines naming a degree, with the year, % / CGPA and institution found on that line or the next one.
     *
     * @return list<array{qualification: string, institution: string|null, year_of_passing: int|null, percentage_or_cgpa: float|null}>
     */
    private function educations(): array
    {
        $educations = [];

        foreach ($this->lines as $index => $line) {
            if (mb_strlen($line) > 250) {
                continue;
            }

            foreach (self::DEGREES as $qualification => $pattern) {
                if (isset($educations[$qualification]) || ! preg_match('/(?<![\p{L}])(?:'.$pattern.')(?![\p{L}])/iu', $line)) {
                    continue;
                }

                $context = $line.' '.($this->lines[$index + 1] ?? '');
                preg_match_all('/\b(19[5-9]\d|20[0-4]\d)\b/', $context, $years);
                $score = preg_match('/(\d{1,2}(?:\.\d{1,2})?)\s*(%|cgpa|gpa|percent)/iu', $context, $m) ? (float) $m[1] : null;
                $institution = preg_match('/([\p{L}.,&\' ]*(?:university|college|institute|school|iit|nit|iiit)[\p{L}.,&\' ]*)/iu', $context, $i) ? trim($i[1], ' ,.') : null;

                $educations[$qualification] = [
                    'qualification' => $qualification,
                    'institution' => $institution ? Str::limit($institution, 255, '') : null,
                    'year_of_passing' => $years[1] ? (int) max($years[1]) : null,
                    'percentage_or_cgpa' => $score !== null && $score <= 100 ? $score : null,
                ];
                break;
            }

            if (count($educations) >= 6) {
                break;
            }
        }

        return array_values($educations);
    }

    /**
     * The highest qualification found, e.g. "MCA" (DEGREES is ordered highest first).
     *
     * @param  list<array{qualification: string, institution: string|null, year_of_passing: int|null, percentage_or_cgpa: float|null}>  $educations
     */
    private function highestQualification(array $educations): ?string
    {
        $found = array_column($educations, 'qualification');

        foreach (array_keys(self::DEGREES) as $qualification) {
            if (in_array($qualification, $found, true)) {
                return $qualification;
            }
        }

        return null;
    }

    private function short(?string $value): ?string
    {
        return $value === null || mb_strlen($value) > 200 ? null : $value;
    }
}
