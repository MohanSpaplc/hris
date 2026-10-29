<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientManager;
use App\Models\Country;
use App\Models\PracticeArea;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * 5 sample records each for countries, practice areas, clients, client managers and vendors.
 * Company names and emails are fictional (.example domain). Safe to run more than once.
 */
class MasterDataSeeder extends Seeder
{
    private const COUNTRIES = [
        ['India', 'IN', 'IND', '🇮🇳', 'INR', 'Indian Rupee', '₹', '+91', 'Asia/Kolkata'],
        ['United States', 'US', 'USA', '🇺🇸', 'USD', 'US Dollar', '$', '+1', 'America/New_York'],
        ['United Kingdom', 'GB', 'GBR', '🇬🇧', 'GBP', 'Pound Sterling', '£', '+44', 'Europe/London'],
        ['United Arab Emirates', 'AE', 'ARE', '🇦🇪', 'AED', 'UAE Dirham', 'د.إ', '+971', 'Asia/Dubai'],
        ['Singapore', 'SG', 'SGP', '🇸🇬', 'SGD', 'Singapore Dollar', 'S$', '+65', 'Asia/Singapore'],
    ];

    private const PRACTICE_AREAS = [
        ['Java & Microservices', 'JAVA'],
        ['.NET & Azure', 'DOTNET'],
        ['Data & Analytics', 'DATA'],
        ['Cloud & DevOps', 'CLOUD'],
        ['Quality Assurance', 'QA'],
    ];

    /**
     * [name, country code, email, phone, address, [manager name, manager email, manager phone]]
     */
    private const CLIENTS = [
        ['Northwind Technologies Pvt Ltd', 'IN', 'contact@northwind.example', '+91 80 4000 1000', 'Whitefield, Bengaluru, Karnataka 560066',
            ['Meera Joshi', 'meera.joshi@northwind.example', '+91 98450 11111']],
        ['Bluepeak Systems Inc', 'US', 'hello@bluepeak.example', '+1 212 555 0142', '350 Fifth Avenue, New York, NY 10118',
            ['Daniel Carter', 'daniel.carter@bluepeak.example', '+1 646 555 0199']],
        ['Harbourline Digital Ltd', 'GB', 'info@harbourline.example', '+44 20 7946 0321', '1 Canada Square, London E14 5AB',
            ['Olivia Bennett', 'olivia.bennett@harbourline.example', '+44 7700 900123']],
        ['Sandstone Solutions LLC', 'AE', 'contact@sandstone.example', '+971 4 555 0110', 'Business Bay, Dubai',
            ['Faisal Rahman', 'faisal.rahman@sandstone.example', '+971 50 555 0177']],
        ['Lionbridge Cloud Pte Ltd', 'SG', 'enquiry@lionbridgecloud.example', '+65 6555 0188', '8 Marina View, Singapore 018960',
            ['Wei Ling Tan', 'weiling.tan@lionbridgecloud.example', '+65 9555 0144']],
    ];

    private const VENDORS = [
        ['TalentBridge Staffing Solutions', 'accounts@talentbridge.example', '+91 22 6100 2000', 'Rohit Desai'],
        ['HireSphere Consulting', 'partners@hiresphere.example', '+91 40 6700 3100', 'Lakshmi Prasad'],
        ['NextGen Recruiters', 'vendor@nextgenrecruit.example', '+91 20 6600 4200', 'Amit Kulkarni'],
        ['CoreSkill Workforce', 'contact@coreskill.example', '+91 44 6500 5300', 'Divya Raman'],
        ['PrimeTalent Partners', 'hello@primetalent.example', '+91 124 450 6400', 'Sanjay Malhotra'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = User::query()->whereRelation('role', 'code', Role::HR)->oldest('id')->first();

        if (! $owner) {
            $this->command?->warn('MasterDataSeeder skipped: no HR user exists (set HR_ADMIN_EMAIL and run HrAdminSeeder first).');

            return;
        }

        foreach (self::COUNTRIES as [$name, $code, $iso3Code, $flag, $currencyCode, $currencyName, $currencySymbol, $dialCode, $timezone]) {
            Country::updateOrCreate(['code' => $code], [
                'name' => $name,
                'iso3_code' => $iso3Code,
                'flag' => $flag,
                'currency_code' => $currencyCode,
                'currency_name' => $currencyName,
                'currency_symbol' => $currencySymbol,
                'dial_code' => $dialCode,
                'timezone' => $timezone,
            ]);
        }

        foreach (self::PRACTICE_AREAS as [$name, $code]) {
            PracticeArea::updateOrCreate(['code' => $code], ['name' => $name, 'head_id' => $owner->id]);
        }

        foreach (self::CLIENTS as [$name, $countryCode, $email, $phone, $address, [$managerName, $managerEmail, $managerPhone]]) {
            $client = Client::firstOrNew(['name' => $name]);
            $client->fill([
                'country_id' => Country::where('code', $countryCode)->value('id'),
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
            ]);
            $client->created_by ??= $owner->id;
            $client->save();

            $manager = ClientManager::firstOrNew(['client_id' => $client->id, 'email' => $managerEmail]);
            $manager->fill(['name' => $managerName, 'phone' => $managerPhone]);
            $manager->created_by ??= $owner->id;
            $manager->save();
        }

        foreach (self::VENDORS as [$name, $email, $phone, $contactPerson]) {
            $vendor = Vendor::firstOrNew(['name' => $name]);
            $vendor->fill(['email' => $email, 'phone' => $phone, 'contact_person' => $contactPerson]);
            $vendor->created_by ??= $owner->id;
            $vendor->save();
        }
    }
}
