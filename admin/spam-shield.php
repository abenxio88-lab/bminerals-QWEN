<?php
/**
 * Balochistan Minerals - Anti-Spam & Automated Bot Defense Shield
 * Intercepts malicious web crawlers, link-injection bots, and fake leads
 * before they reach the database or outbound email dispatchers.
 */

if (!defined('BM_SPAM_SHIELD_LOADED')) {
    define('BM_SPAM_SHIELD_LOADED', true);

    /**
     * Inspects inbound form payloads for bot and spam signatures.
     * Returns null if the submission is clean, or a descriptive reason string if spam.
     *
     * @param array $data Raw $_POST data
     * @return string|null Reason if spam, null if clean
     */
    function detectSpamSubmission(array $data): ?string {
        // 1. Classic hidden honeypot field
        if (!empty($data['botcheck'])) {
            return 'Honeypot trap triggered (botcheck field filled)';
        }

        $rawName    = (string)($data['name'] ?? $data['reviewer_name'] ?? '');
        $rawCompany = (string)($data['company'] ?? $data['company_name'] ?? '');
        $rawEmail   = strtolower(trim((string)($data['email'] ?? '')));
        $rawPhone   = (string)($data['phone'] ?? $data['whatsapp'] ?? '');
        $rawMessage = (string)($data['message'] ?? $data['review_text'] ?? '');

        // 2. HTML / Script / Markdown URL injection in Name, Company, or Phone fields
        // Legitimate buyer names/companies never contain HTML tags or URLs
        $htmlUrlPattern = '#(<\s*[a-z!/]|href\s*=|https?://|www\.|\[url|<script|<iframe|<style)#i';
        if (preg_match($htmlUrlPattern, $rawName)) {
            return 'HTML or URL injection in Name field';
        }
        if (preg_match($htmlUrlPattern, $rawCompany)) {
            return 'HTML or URL injection in Company field';
        }
        if (preg_match($htmlUrlPattern, $rawPhone)) {
            return 'HTML or URL injection in Phone field';
        }

        // 3. Known spam bot keywords & darknet payloads (case-insensitive)
        $blacklistedKeywords = [
            'scam service',
            'myxa.cc',
            'carding',
            'darknet',
            'seo ranking',
            'ranking service',
            'backlink service',
            'seo agency',
            'viagra',
            'cialis',
            'casino online',
            'poker online',
            'crypto recovery',
            'whatsapp blast',
            'bulk email blast',
            'telegram bot service',
            'exploit',
        ];
        $combinedText = strtolower($rawName . ' ' . $rawCompany . ' ' . $rawMessage);
        foreach ($blacklistedKeywords as $kw) {
            if (strpos($combinedText, $kw) !== false) {
                return "Blacklisted spam keyword detected: '{$kw}'";
            }
        }

        // 4. Automated bot tracking signatures (e.g. XRumer hash 'hs=4bc4f3c8...')
        if (preg_match('/hs=[a-f0-9]{16,}/i', $rawName . ' ' . $rawMessage)) {
            return 'Automated bot tracking signature detected (XRumer hash)';
        }

        // 5. Disposable / throwaway spam email providers & invalid email characters
        $disposableDomains = [
            'merepost.com',
            'mailinator.com',
            'guerrillamail.com',
            'tempmail.com',
            '10minutemail.com',
            'throwawaymail.com',
            'sharklasers.com',
            'yopmail.com',
            'dispostable.com',
            'trashmail.com',
            'fakeinbox.com',
        ];
        $emailParts = explode('@', $rawEmail);
        if (count($emailParts) === 2) {
            $emailDomain = trim($emailParts[1]);
            if (in_array($emailDomain, $disposableDomains, true)) {
                return "Disposable temporary email domain rejected: {$emailDomain}";
            }
            // Suspicious characters in local part (e.g. 2ry~e95v0d4d2y@merepost.com)
            if (preg_match('/[~`\^]/', $emailParts[0])) {
                return 'Malformed email address syntax detected';
            }
        }

        // 6. Excessive URLs in message body (more than 2 links)
        if (preg_match_all('/https?:\/\//i', $rawMessage, $m) && count($m[0]) > 2) {
            return 'Excessive external links in message body (>2 URLs)';
        }

        // 7. Time-to-submit verification (if form_ts token was provided by frontend)
        if (!empty($data['form_ts'])) {
            $formTs = (int)$data['form_ts'];
            $now = time();
            // If submitted faster than 2 seconds or timestamp is in the future (>60s)
            if ($formTs > 0 && ($now - $formTs < 2 || $formTs > $now + 60)) {
                return 'Submission speed too fast (humanly impossible time-to-submit)';
            }
        }

        return null;
    }
}
