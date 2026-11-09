<?php
/**
 * Balochistan Minerals - Executive High-Definition Vector Flag Helper
 * Pure SVG vectors with zero external network requests.
 * Renders crisp, retina-ready country flags for international trade partners.
 */

if (!function_exists('getCountryFlagSvg')) {
    function getCountryFlagSvg(string $country): string {
        $clean = strtolower(trim($country));
        $svgAttrs = 'width="18" height="12" style="width:18px !important;height:12px !important;display:inline-block;vertical-align:middle;object-fit:cover;border-radius:2px;flex-shrink:0;" class="country-flag-svg"';

        // Germany
        if (str_contains($clean, 'german') || $clean === 'de') {
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
        if (str_contains($clean, 'kingdom') || str_contains($clean, 'britain') || $clean === 'uk' || $clean === 'gb') {
            return '<svg viewBox="0 0 60 30" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="United Kingdom"><rect width="60" height="30" fill="#012169"/><path d="M0,0 L60,30 M60,0 L0,30" stroke="#FFFFFF" stroke-width="6"/><path d="M0,0 L60,30 M60,0 L0,30" stroke="#C8102E" stroke-width="4"/><path d="M30,0 v30 M0,15 h60" stroke="#FFFFFF" stroke-width="10"/><path d="M30,0 v30 M0,15 h60" stroke="#C8102E" stroke-width="6"/></svg>';
        }

        // France
        if (str_contains($clean, 'france') || str_contains($clean, 'french') || $clean === 'fr') {
            return '<svg viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' aria-label="France"><rect width="1" height="2" x="0" fill="#002654"/><rect width="1" height="2" x="1" fill="#FFFFFF"/><rect width="1" height="2" x="2" fill="#ED2939"/></svg>';
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

        // Default / Global International
        return '<svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" ' . $svgAttrs . ' fill="#64748B" aria-label="International"><circle cx="8" cy="8" r="7.5" fill="#F1F5F9" stroke="#CBD5E1"/><path d="M8 0.5a7.5 7.5 0 1 0 0 15A7.5 7.5 0 0 0 8 0.5zm6.5 7H12.3a12.5 12.5 0 0 0-.85-3.95A6.5 6.5 0 0 1 14.5 7.5zM8 1.5c.67 1.12 1.21 2.58 1.44 4.6H6.56C6.79 4.08 7.33 2.62 8 1.5zM1.5 8.5h2.2c.17 1.4.54 2.73.85 3.95A6.5 6.5 0 0 1 1.5 8.5zm2.2-2H1.5A6.5 6.5 0 0 1 4.55 2.55c-.31 1.22-.68 2.55-.85 3.95zM8 14.5c-.67-1.12-1.21-2.58-1.44-4.6h2.88c-.23 2.02-.77 3.48-1.44 4.6zm1.72-6.48H6.28C6.2 7.38 6.13 6.76 6.13 6.13s.07-1.25.15-1.89h3.44c.08.64.15 1.26.15 1.89s-.07 1.25-.15 1.89zm.83 3.93c.31-1.22.68-2.55.85-3.95h2.2a6.5 6.5 0 0 1-3.05 3.95z"/></svg>';
    }
}
