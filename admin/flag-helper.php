<?php
/**
 * Balochistan Minerals - Executive High-Definition Vector Flag & Country Origin Engine
 * Pure SVG vectors with zero external network requests.
 * Automatically resolves country origin from Email TLD, Phone/WhatsApp prefix, Company suffixes, or explicit inputs.
 */

if (!function_exists('normalizeCountryName')) {
    function normalizeCountryName(string $country): string {
        $c = strtolower(trim($country));
        if (empty($c) || $c === 'international' || $c === 'unknown' || $c === 'n/a') {
            return 'International';
        }
        if (str_contains($c, 'france') || str_contains($c, 'french') || $c === 'fr') return 'France';
        if (str_contains($c, 'german') || $c === 'de' || str_contains($c, 'deutschland')) return 'Germany';
        if (str_contains($c, 'china') || str_contains($c, 'chinese') || $c === 'cn') return 'China';
        if (str_contains($c, 'uae') || str_contains($c, 'emirates') || str_contains($c, 'dubai') || $c === 'ae') return 'UAE';
        if (str_contains($c, 'japan') || $c === 'jp') return 'Japan';
        if (str_contains($c, 'kingdom') || str_contains($c, 'britain') || $c === 'uk' || $c === 'gb' || str_contains($c, 'england')) return 'United Kingdom';
        if (str_contains($c, 'united states') || str_contains($c, 'usa') || $c === 'us' || str_contains($c, 'america')) return 'United States';
        if (str_contains($c, 'austria') || $c === 'at') return 'Austria';
        if (str_contains($c, 'belgium') || $c === 'be') return 'Belgium';
        if (str_contains($c, 'pakistan') || $c === 'pk') return 'Pakistan';
        if (str_contains($c, 'saudi') || $c === 'sa') return 'Saudi Arabia';
        if (str_contains($c, 'turkey') || str_contains($c, 'türkiye') || $c === 'tr') return 'Turkey';
        if (str_contains($c, 'switzerland') || str_contains($c, 'swiss') || $c === 'ch') return 'Switzerland';
        if (str_contains($c, 'italy') || str_contains($c, 'italian') || $c === 'it') return 'Italy';
        if (str_contains($c, 'netherlands') || str_contains($c, 'holland') || str_contains($c, 'dutch') || $c === 'nl') return 'Netherlands';
        if (str_contains($c, 'spain') || $c === 'es') return 'Spain';
        if (str_contains($c, 'india') || $c === 'in') return 'India';
        if (str_contains($c, 'korea') || $c === 'kr') return 'South Korea';
        if (str_contains($c, 'singapore') || $c === 'sg') return 'Singapore';
        if (str_contains($c, 'canada') || $c === 'ca') return 'Canada';
        if (str_contains($c, 'australia') || $c === 'au') return 'Australia';
        if (str_contains($c, 'oman') || $c === 'om') return 'Oman';
        if (str_contains($c, 'qatar') || $c === 'qa') return 'Qatar';
        if (str_contains($c, 'kuwait') || $c === 'kw') return 'Kuwait';
        if (str_contains($c, 'bahrain') || $c === 'bh') return 'Bahrain';
        if (str_contains($c, 'egypt') || $c === 'eg') return 'Egypt';
        if (str_contains($c, 'south africa') || $c === 'za') return 'South Africa';
        if (str_contains($c, 'brazil') || $c === 'br') return 'Brazil';
        if (str_contains($c, 'mexico') || $c === 'mx') return 'Mexico';
        if (str_contains($c, 'poland') || $c === 'pl') return 'Poland';
        if (str_contains($c, 'sweden') || $c === 'se') return 'Sweden';
        if (str_contains($c, 'norway') || $c === 'no') return 'Norway';
        if (str_contains($c, 'denmark') || $c === 'dk') return 'Denmark';
        if (str_contains($c, 'finland') || $c === 'fi') return 'Finland';
        if (str_contains($c, 'greece') || $c === 'gr') return 'Greece';
        if (str_contains($c, 'portugal') || $c === 'pt') return 'Portugal';
        if (str_contains($c, 'ireland') || $c === 'ie') return 'Ireland';
        if (str_contains($c, 'new zealand') || $c === 'nz') return 'New Zealand';
        if (str_contains($c, 'malaysia') || $c === 'my') return 'Malaysia';
        if (str_contains($c, 'thailand') || $c === 'th') return 'Thailand';
        if (str_contains($c, 'vietnam') || $c === 'vn') return 'Vietnam';
        if (str_contains($c, 'indonesia') || $c === 'id') return 'Indonesia';
        if (str_contains($c, 'philippines') || $c === 'ph') return 'Philippines';
        if (str_contains($c, 'taiwan') || $c === 'tw') return 'Taiwan';
        if (str_contains($c, 'hong kong') || $c === 'hk') return 'Hong Kong';

        return ucwords(trim($country));
    }
}

if (!function_exists('detectCountryName')) {
    function detectCountryName(string $country = '', string $email = '', string $phone = '', string $company = '', string $message = ''): string {
        $clean = trim($country);
        if (!empty($clean) && strcasecmp($clean, 'International') !== 0 && strcasecmp($clean, 'Unknown') !== 0 && strcasecmp($clean, 'N/A') !== 0) {
            $norm = normalizeCountryName($clean);
            if ($norm !== 'International') {
                return $norm;
            }
        }

        // 1. Detect from Email Domain & ccTLD (.fr, .de, .cn, .jp, .ae, etc.)
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $domain = strtolower(substr(strrchr($email, "@"), 1));
            $parts = explode('.', $domain);
            $tld = end($parts);
            $prevTld = count($parts) > 2 ? $parts[count($parts) - 2] : '';
            $fullTld = $prevTld ? ($prevTld . '.' . $tld) : $tld;

            $tldMap = [
                'fr' => 'France',
                'de' => 'Germany',
                'cn' => 'China',
                'com.cn' => 'China',
                'jp' => 'Japan',
                'co.jp' => 'Japan',
                'ae' => 'UAE',
                'uk' => 'United Kingdom',
                'co.uk' => 'United Kingdom',
                'us' => 'United States',
                'ca' => 'Canada',
                'au' => 'Australia',
                'com.au' => 'Australia',
                'it' => 'Italy',
                'es' => 'Spain',
                'nl' => 'Netherlands',
                'be' => 'Belgium',
                'ch' => 'Switzerland',
                'at' => 'Austria',
                'sa' => 'Saudi Arabia',
                'tr' => 'Turkey',
                'kr' => 'South Korea',
                'co.kr' => 'South Korea',
                'sg' => 'Singapore',
                'com.sg' => 'Singapore',
                'in' => 'India',
                'co.in' => 'India',
                'pk' => 'Pakistan',
                'om' => 'Oman',
                'qa' => 'Qatar',
                'kw' => 'Kuwait',
                'bh' => 'Bahrain',
                'eg' => 'Egypt',
                'za' => 'South Africa',
                'br' => 'Brazil',
                'mx' => 'Mexico',
                'pl' => 'Poland',
                'se' => 'Sweden',
                'no' => 'Norway',
                'dk' => 'Denmark',
                'fi' => 'Finland',
                'gr' => 'Greece',
                'pt' => 'Portugal',
                'ie' => 'Ireland',
                'nz' => 'New Zealand',
                'my' => 'Malaysia',
                'th' => 'Thailand',
                'vn' => 'Vietnam',
                'id' => 'Indonesia',
                'ph' => 'Philippines',
                'tw' => 'Taiwan',
                'hk' => 'Hong Kong'
            ];

            if (isset($tldMap[$fullTld])) return $tldMap[$fullTld];
            if (isset($tldMap[$tld])) return $tldMap[$tld];
        }

        // 2. Detect from WhatsApp / Phone Dialing Code (+33, +49, +86, etc.)
        if (!empty($phone)) {
            $p = preg_replace('/[^0-9+]/', '', trim($phone));
            if (str_starts_with($p, '+') || str_starts_with($p, '00')) {
                $p = ltrim($p, '+');
                if (str_starts_with($p, '00')) $p = substr($p, 2);

                $prefixMap = [
                    '33' => 'France',
                    '49' => 'Germany',
                    '86' => 'China',
                    '971' => 'UAE',
                    '81' => 'Japan',
                    '44' => 'United Kingdom',
                    '1' => 'United States',
                    '39' => 'Italy',
                    '34' => 'Spain',
                    '31' => 'Netherlands',
                    '32' => 'Belgium',
                    '41' => 'Switzerland',
                    '43' => 'Austria',
                    '966' => 'Saudi Arabia',
                    '90' => 'Turkey',
                    '82' => 'South Korea',
                    '65' => 'Singapore',
                    '91' => 'India',
                    '92' => 'Pakistan',
                    '968' => 'Oman',
                    '974' => 'Qatar',
                    '965' => 'Kuwait',
                    '973' => 'Bahrain',
                    '20' => 'Egypt',
                    '27' => 'South Africa',
                    '55' => 'Brazil',
                    '52' => 'Mexico',
                    '48' => 'Poland',
                    '46' => 'Sweden',
                    '47' => 'Norway',
                    '45' => 'Denmark',
                    '358' => 'Finland',
                    '30' => 'Greece',
                    '351' => 'Portugal',
                    '353' => 'Ireland',
                    '64' => 'New Zealand',
                    '60' => 'Malaysia',
                    '66' => 'Thailand',
                    '84' => 'Vietnam',
                    '62' => 'Indonesia',
                    '63' => 'Philippines',
                    '886' => 'Taiwan',
                    '852' => 'Hong Kong'
                ];

                foreach ([4, 3, 2, 1] as $len) {
                    $sub = substr($p, 0, $len);
                    if (isset($prefixMap[$sub])) {
                        return $prefixMap[$sub];
                    }
                }
            }
        }

        // 3. Detect from Company Name or Text
        $textToScan = strtolower($company . ' ' . $message);
        if (preg_match('/\b(france|french|paris|marseille|dunkirk|lyon|le havre)\b/i', $textToScan) || preg_match('/\b(sas|sarl)\b/i', $company)) {
            return 'France';
        }
        if (preg_match('/\b(germany|german|deutschland|hamburg|duisburg|bremen|frankfurt|berlin|ruhr)\b/i', $textToScan) || preg_match('/\b(gmbh|ag|kgaa)\b/i', $company)) {
            return 'Germany';
        }
        if (preg_match('/\b(china|chinese|tianjin|shanghai|ningbo|qingdao|shenzhen|beijing|guangzhou)\b/i', $textToScan)) {
            return 'China';
        }
        if (preg_match('/\b(uae|emirates|dubai|abu dhabi|sharjah|jebel ali)\b/i', $textToScan) || preg_match('/\b(fze|fzco|dmcc|jafza)\b/i', $company)) {
            return 'UAE';
        }
        if (preg_match('/\b(japan|japanese|tokyo|chiba|osaka|yokohama|nagoya)\b/i', $textToScan) || preg_match('/\b(kabushiki|k\.k\.)\b/i', $company)) {
            return 'Japan';
        }
        if (preg_match('/\b(united kingdom|britain|british|london|liverpool|southampton|england|scotland)\b/i', $textToScan) || preg_match('/\b(plc|llp)\b/i', $company)) {
            return 'United Kingdom';
        }
        if (preg_match('/\b(united states|usa|america|american|houston|new york|chicago|california|texas)\b/i', $textToScan)) {
            return 'United States';
        }
        if (preg_match('/\b(singapore)\b/i', $textToScan) || preg_match('/\bpte\s*ltd\b/i', $company)) {
            return 'Singapore';
        }
        if (preg_match('/\b(netherlands|holland|dutch|rotterdam|amsterdam)\b/i', $textToScan) || preg_match('/\b(b\.v\.|bv)\b/i', $company)) {
            return 'Netherlands';
        }
        if (preg_match('/\b(belgium|belgian|antwerp|brussels)\b/i', $textToScan)) {
            return 'Belgium';
        }
        if (preg_match('/\b(italy|italian|milan|rome|genoa)\b/i', $textToScan) || preg_match('/\b(s\.p\.a\.|spa|srl)\b/i', $company)) {
            return 'Italy';
        }
        if (preg_match('/\b(spain|spanish|madrid|barcelona|valencia|bilbao)\b/i', $textToScan) || preg_match('/\b(s\.l\.|sl)\b/i', $company)) {
            return 'Spain';
        }
        if (preg_match('/\b(korea|korean|seoul|busan|incheon)\b/i', $textToScan)) {
            return 'South Korea';
        }
        if (preg_match('/\b(saudi|riyadh|jeddah|dammam)\b/i', $textToScan)) {
            return 'Saudi Arabia';
        }
        if (preg_match('/\b(turkey|türkiye|turkish|istanbul|izmir|ankara)\b/i', $textToScan)) {
            return 'Turkey';
        }
        if (preg_match('/\b(canada|canadian|toronto|vancouver|montreal)\b/i', $textToScan)) {
            return 'Canada';
        }
        if (preg_match('/\b(australia|australian|sydney|melbourne|perth|brisbane)\b/i', $textToScan)) {
            return 'Australia';
        }
        if (preg_match('/\b(india|indian|mumbai|delhi|kolkata|chennai)\b/i', $textToScan)) {
            return 'India';
        }
        if (preg_match('/\b(pakistan|karachi|lahore|islamabad|gwadar|quetta)\b/i', $textToScan)) {
            return 'Pakistan';
        }

        return 'International';
    }
}

if (!function_exists('getCountryFlagSvg')) {
    function getCountryFlagSvg(string $country, string $email = '', string $phone = '', string $company = ''): string {
        if (empty($country) || strcasecmp($country, 'International') === 0) {
            $country = detectCountryName($country, $email, $phone, $company);
        }
        $clean = strtolower(trim($country));
        $svgAttrs = 'width="18" height="12" style="width:18px !important;height:12px !important;display:inline-block;vertical-align:middle;object-fit:cover;border-radius:2px;flex-shrink:0;border:none !important;" class="country-flag-svg"';

        // France
        if (str_contains($clean, 'france') || str_contains($clean, 'french') || $clean === 'fr') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="France"><rect width="1" height="2" x="0" fill="#002654"/><rect width="1" height="2" x="1" fill="#FFFFFF"/><rect width="1" height="2" x="2" fill="#ED2939"/></svg>';
        }

        // Germany
        if (str_contains($clean, 'german') || $clean === 'de' || str_contains($clean, 'deutschland')) {
            return '<svg viewBox="0 0 5 3" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Germany"><rect width="5" height="1" y="0" fill="#000000"/><rect width="5" height="1" y="1" fill="#DD0000"/><rect width="5" height="1" y="2" fill="#FFCE00"/></svg>';
        }

        // China
        if (str_contains($clean, 'china') || str_contains($clean, 'chinese') || $clean === 'cn') {
            return '<svg viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="China"><rect width="30" height="20" fill="#DE2910"/><polygon points="5,2 6.2,5.6 2.8,3.2 7.2,3.2 3.8,5.6" fill="#FFDE00"/><polygon points="10,2 10.6,3.8 8.8,2.6 11.2,2.6 9.4,3.8" fill="#FFDE00"/><polygon points="12,4 12.6,5.8 10.8,4.6 13.2,4.6 11.4,5.8" fill="#FFDE00"/><polygon points="12,7 12.6,8.8 10.8,7.6 13.2,7.6 11.4,8.8" fill="#FFDE00"/><polygon points="10,9 10.6,10.8 8.8,9.6 11.2,9.6 9.4,10.8" fill="#FFDE00"/></svg>';
        }

        // Japan
        if (str_contains($clean, 'japan') || $clean === 'jp') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Japan"><rect width="3" height="2" fill="#FFFFFF"/><circle cx="1.5" cy="1" r="0.6" fill="#BC002D"/></svg>';
        }

        // UAE / United Arab Emirates
        if (str_contains($clean, 'uae') || str_contains($clean, 'emirates') || str_contains($clean, 'dubai') || $clean === 'ae') {
            return '<svg viewBox="0 0 4 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="UAE"><rect width="4" height="0.67" y="0" fill="#00732F"/><rect width="4" height="0.67" y="0.67" fill="#FFFFFF"/><rect width="4" height="0.66" y="1.34" fill="#000000"/><rect width="1" height="2" x="0" fill="#FF0000"/></svg>';
        }

        // United States / USA
        if (str_contains($clean, 'united states') || str_contains($clean, 'usa') || $clean === 'us' || str_contains($clean, 'america')) {
            return '<svg viewBox="0 0 19 10" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="United States"><rect width="19" height="10" fill="#B22234"/><rect width="19" height="0.77" y="0.77" fill="#FFFFFF"/><rect width="19" height="0.77" y="2.31" fill="#FFFFFF"/><rect width="19" height="0.77" y="3.85" fill="#FFFFFF"/><rect width="19" height="0.77" y="5.39" fill="#FFFFFF"/><rect width="19" height="0.77" y="6.93" fill="#FFFFFF"/><rect width="19" height="0.77" y="8.47" fill="#FFFFFF"/><rect width="7.6" height="5.39" fill="#3C3B6E"/><circle cx="1.9" cy="1.35" r="0.4" fill="#FFFFFF"/><circle cx="3.8" cy="1.35" r="0.4" fill="#FFFFFF"/><circle cx="5.7" cy="1.35" r="0.4" fill="#FFFFFF"/><circle cx="2.85" cy="2.7" r="0.4" fill="#FFFFFF"/><circle cx="4.75" cy="2.7" r="0.4" fill="#FFFFFF"/><circle cx="1.9" cy="4.05" r="0.4" fill="#FFFFFF"/><circle cx="3.8" cy="4.05" r="0.4" fill="#FFFFFF"/><circle cx="5.7" cy="4.05" r="0.4" fill="#FFFFFF"/></svg>';
        }

        // United Kingdom / Great Britain / UK
        if (str_contains($clean, 'kingdom') || str_contains($clean, 'britain') || $clean === 'uk' || $clean === 'gb' || str_contains($clean, 'england')) {
            return '<svg viewBox="0 0 60 30" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="United Kingdom"><rect width="60" height="30" fill="#012169"/><path d="M0,0 L60,30 M60,0 L0,30" stroke="#FFFFFF" stroke-width="6"/><path d="M0,0 L60,30 M60,0 L0,30" stroke="#C8102E" stroke-width="4"/><path d="M30,0 v30 M0,15 h60" stroke="#FFFFFF" stroke-width="10"/><path d="M30,0 v30 M0,15 h60" stroke="#C8102E" stroke-width="6"/></svg>';
        }

        // Austria
        if (str_contains($clean, 'austria') || $clean === 'at') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Austria"><rect width="3" height="0.67" y="0" fill="#ED2939"/><rect width="3" height="0.67" y="0.67" fill="#FFFFFF"/><rect width="3" height="0.66" y="1.34" fill="#ED2939"/></svg>';
        }

        // Belgium
        if (str_contains($clean, 'belgium') || $clean === 'be') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Belgium"><rect width="1" height="2" x="0" fill="#000000"/><rect width="1" height="2" x="1" fill="#FDDA24"/><rect width="1" height="2" x="2" fill="#EF3340"/></svg>';
        }

        // Pakistan
        if (str_contains($clean, 'pakistan') || $clean === 'pk') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Pakistan"><rect width="3" height="2" fill="#01411C"/><rect width="0.75" height="2" fill="#FFFFFF"/><circle cx="1.95" cy="1" r="0.6" fill="#FFFFFF"/><circle cx="2.1" cy="0.9" r="0.55" fill="#01411C"/><polygon points="2.1,0.7 2.2,0.9 2,0.8 2.3,0.8 2.1,0.95" fill="#FFFFFF"/></svg>';
        }

        // Saudi Arabia
        if (str_contains($clean, 'saudi') || $clean === 'sa') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Saudi Arabia"><rect width="3" height="2" fill="#006C35"/><rect x="0.6" y="0.65" width="1.8" height="0.3" rx="0.1" fill="#FFFFFF" opacity="0.9"/><path d="M0.8 1.35 H2.2 L2.0 1.25 H0.8 Z" fill="#FFFFFF"/></svg>';
        }

        // Turkey
        if (str_contains($clean, 'turkey') || str_contains($clean, 'türkiye') || $clean === 'tr') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Turkey"><rect width="3" height="2" fill="#E30A17"/><circle cx="1.2" cy="1" r="0.5" fill="#FFFFFF"/><circle cx="1.32" cy="1" r="0.4" fill="#E30A17"/><polygon points="1.6,1 1.8,0.93 1.7,1.08 1.7,0.92 1.8,1.07" fill="#FFFFFF"/></svg>';
        }

        // Switzerland
        if (str_contains($clean, 'switzerland') || str_contains($clean, 'swiss') || $clean === 'ch') {
            return '<svg viewBox="0 0 1 1" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Switzerland"><rect width="1" height="1" fill="#D52B1E"/><rect x="0.4" y="0.2" width="0.2" height="0.6" fill="#FFFFFF"/><rect x="0.2" y="0.4" width="0.6" height="0.2" fill="#FFFFFF"/></svg>';
        }

        // Italy
        if (str_contains($clean, 'italy') || str_contains($clean, 'italian') || $clean === 'it') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Italy"><rect width="1" height="2" x="0" fill="#009246"/><rect width="1" height="2" x="1" fill="#FFFFFF"/><rect width="1" height="2" x="2" fill="#CE2B37"/></svg>';
        }

        // Netherlands
        if (str_contains($clean, 'netherlands') || str_contains($clean, 'holland') || str_contains($clean, 'dutch') || $clean === 'nl') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Netherlands"><rect width="3" height="0.67" y="0" fill="#AE1C28"/><rect width="3" height="0.67" y="0.67" fill="#FFFFFF"/><rect width="3" height="0.66" y="1.34" fill="#21468B"/></svg>';
        }

        // Spain
        if (str_contains($clean, 'spain') || $clean === 'es') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Spain"><rect width="3" height="0.5" y="0" fill="#AA151B"/><rect width="3" height="1" y="0.5" fill="#F1BF00"/><rect width="3" height="0.5" y="1.5" fill="#AA151B"/></svg>';
        }

        // India
        if (str_contains($clean, 'india') || $clean === 'in') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="India"><rect width="3" height="0.67" y="0" fill="#FF9933"/><rect width="3" height="0.67" y="0.67" fill="#FFFFFF"/><rect width="3" height="0.66" y="1.34" fill="#128807"/><circle cx="1.5" cy="1" r="0.2" fill="#000080"/></svg>';
        }

        // South Korea
        if (str_contains($clean, 'korea') || $clean === 'kr') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="South Korea"><rect width="3" height="2" fill="#FFFFFF"/><circle cx="1.5" cy="1" r="0.5" fill="#C60C30"/><path d="M1 1 A0.5 0.5 0 0 0 2 1 A0.25 0.25 0 0 0 1.5 1 A0.25 0.25 0 0 1 1 1" fill="#003478"/></svg>';
        }

        // Singapore
        if (str_contains($clean, 'singapore') || $clean === 'sg') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Singapore"><rect width="3" height="1" y="0" fill="#ED2939"/><rect width="3" height="1" y="1" fill="#FFFFFF"/><circle cx="0.6" cy="0.5" r="0.35" fill="#FFFFFF"/><circle cx="0.7" cy="0.5" r="0.35" fill="#ED2939"/></svg>';
        }

        // Canada
        if (str_contains($clean, 'canada') || $clean === 'ca') {
            return '<svg viewBox="0 0 4 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Canada"><rect width="1" height="2" x="0" fill="#FF0000"/><rect width="2" height="2" x="1" fill="#FFFFFF"/><rect width="1" height="2" x="3" fill="#FF0000"/><polygon points="2,0.4 2.2,0.8 2.5,0.7 2.3,1.1 2.6,1.2 2.2,1.3 2.1,1.7 1.9,1.7 1.8,1.3 1.4,1.2 1.7,1.1 1.5,0.7 1.8,0.8" fill="#FF0000"/></svg>';
        }

        // Australia
        if (str_contains($clean, 'australia') || $clean === 'au') {
            return '<svg viewBox="0 0 4 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Australia"><rect width="4" height="2" fill="#00008B"/><rect width="1.6" height="0.9" fill="#012169"/><path d="M0,0 L1.6,0.9 M1.6,0 L0,0.9" stroke="#FFFFFF" stroke-width="0.2"/><path d="M0.8,0 v0.9 M0,0.45 h1.6" stroke="#FFFFFF" stroke-width="0.3"/><path d="M0.8,0 v0.9 M0,0.45 h1.6" stroke="#C8102E" stroke-width="0.18"/><circle cx="0.8" cy="1.45" r="0.2" fill="#FFFFFF"/><circle cx="3" cy="0.5" r="0.08" fill="#FFFFFF"/><circle cx="3.4" cy="0.8" r="0.08" fill="#FFFFFF"/><circle cx="3" cy="1.1" r="0.08" fill="#FFFFFF"/><circle cx="2.6" cy="0.8" r="0.08" fill="#FFFFFF"/><circle cx="3.2" cy="1.4" r="0.05" fill="#FFFFFF"/></svg>';
        }

        // Oman
        if (str_contains($clean, 'oman') || $clean === 'om') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Oman"><rect width="3" height="0.67" y="0" fill="#FFFFFF"/><rect width="3" height="0.67" y="0.67" fill="#C8102E"/><rect width="3" height="0.66" y="1.34" fill="#008000"/><rect width="0.75" height="2" fill="#C8102E"/></svg>';
        }

        // Qatar
        if (str_contains($clean, 'qatar') || $clean === 'qa') {
            return '<svg viewBox="0 0 5 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Qatar"><rect width="5" height="2" fill="#8D1B3D"/><rect width="1.6" height="2" fill="#FFFFFF"/></svg>';
        }

        // Kuwait
        if (str_contains($clean, 'kuwait') || $clean === 'kw') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Kuwait"><rect width="3" height="0.67" y="0" fill="#007A3D"/><rect width="3" height="0.67" y="0.67" fill="#FFFFFF"/><rect width="3" height="0.66" y="1.34" fill="#CE1126"/><polygon points="0,0 0.8,0.67 0.8,1.33 0,2" fill="#000000"/></svg>';
        }

        // Bahrain
        if (str_contains($clean, 'bahrain') || $clean === 'bh') {
            return '<svg viewBox="0 0 5 3" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Bahrain"><rect width="5" height="3" fill="#CE1126"/><rect width="1.5" height="3" fill="#FFFFFF"/></svg>';
        }

        // Egypt
        if (str_contains($clean, 'egypt') || $clean === 'eg') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Egypt"><rect width="3" height="0.67" y="0" fill="#CE1126"/><rect width="3" height="0.67" y="0.67" fill="#FFFFFF"/><rect width="3" height="0.66" y="1.34" fill="#000000"/><circle cx="1.5" cy="1" r="0.2" fill="#C09A35"/></svg>';
        }

        // South Africa
        if (str_contains($clean, 'south africa') || $clean === 'za') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="South Africa"><rect width="3" height="1" y="0" fill="#E03C31"/><rect width="3" height="1" y="1" fill="#001489"/><path d="M0,0 L1.2,1 L0,2" fill="#000000"/><path d="M0,0 L1.2,1 L3,1" stroke="#007749" stroke-width="0.3" fill="none"/></svg>';
        }

        // Brazil
        if (str_contains($clean, 'brazil') || $clean === 'br') {
            return '<svg viewBox="0 0 10 7" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Brazil"><rect width="10" height="7" fill="#009C3B"/><polygon points="5,0.7 9.3,3.5 5,6.3 0.7,3.5" fill="#FFDF00"/><circle cx="5" cy="3.5" r="1.6" fill="#002776"/></svg>';
        }

        // Sweden
        if (str_contains($clean, 'sweden') || $clean === 'se') {
            return '<svg viewBox="0 0 16 10" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Sweden"><rect width="16" height="10" fill="#006AA7"/><rect x="5" width="2" height="10" fill="#FECC00"/><rect y="4" width="16" height="2" fill="#FECC00"/></svg>';
        }

        // Norway
        if (str_contains($clean, 'norway') || $clean === 'no') {
            return '<svg viewBox="0 0 22 16" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Norway"><rect width="22" height="16" fill="#BA0C2F"/><path d="M0,8 H22 M8,0 V16" stroke="#FFFFFF" stroke-width="4"/><path d="M0,8 H22 M8,0 V16" stroke="#00205B" stroke-width="2"/></svg>';
        }

        // Poland
        if (str_contains($clean, 'poland') || $clean === 'pl') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="Poland"><rect width="3" height="1" y="0" fill="#FFFFFF"/><rect width="3" height="1" y="1" fill="#DC143C"/></svg>';
        }

        // Default / Global International (Border-Free Crisp Globe)
        return '<svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' fill="#64748B" aria-label="International"><circle cx="8" cy="8" r="7.5" fill="#F1F5F9"/><path d="M8 0.5a7.5 7.5 0 1 0 0 15A7.5 7.5 0 0 0 8 0.5zm6.5 7H12.3a12.5 12.5 0 0 0-.85-3.95A6.5 6.5 0 0 1 14.5 7.5zM8 1.5c.67 1.12 1.21 2.58 1.44 4.6H6.56C6.79 4.08 7.33 2.62 8 1.5zM1.5 8.5h2.2c.17 1.4.54 2.73.85 3.95A6.5 6.5 0 0 1 1.5 8.5zm2.2-2H1.5A6.5 6.5 0 0 1 4.55 2.55c-.31 1.22-.68 2.55-.85 3.95zM8 14.5c-.67-1.12-1.21-2.58-1.44-4.6h2.88c-.23 2.02-.77 3.48-1.44 4.6zm1.72-6.48H6.28C6.2 7.38 6.13 6.76 6.13 6.13s.07-1.25.15-1.89h3.44c.08.64.15 1.26.15 1.89s-.07 1.25-.15 1.89zm.83 3.93c.31-1.22.68-2.55.85-3.95h2.2a6.5 6.5 0 0 1-3.05 3.95z"/></svg>';
    }
}
