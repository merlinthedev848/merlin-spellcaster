<?php
declare(strict_types=1);

/**
 * Deliverability Suite & Verifier Module
 */
class DeliverabilityVerifier {
    /**
     * Verify single email address deliverability & MX records
     */
    public static function verifyEmail(string $email): array {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'invalid', 'reason' => 'Malformed email address format'];
        }

        $domain = explode('@', $email)[1] ?? '';
        if (empty($domain)) {
            return ['status' => 'invalid', 'reason' => 'Missing domain'];
        }

        // Disposable domain check
        $disposable = ['mailinator.com', '10minutemail.com', 'tempmail.com', 'guerrillamail.com', 'trashmail.com', 'sharklasers.com', 'yopmail.com'];
        if (in_array($domain, $disposable, true)) {
            return ['status' => 'disposable', 'reason' => 'Disposable temporary email provider'];
        }

        // DNS MX Check
        if (!checkdnsrr($domain, 'MX')) {
            return ['status' => 'bounced', 'reason' => 'Domain does not have valid MX mail servers'];
        }

        return ['status' => 'valid', 'reason' => 'MX records verified & deliverable'];
    }

    /**
     * Batch verify all unverified contacts in CRM
     */
    public static function processBatch(int $limit = 100): array {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id, email FROM subscribers WHERE status = 'active' ORDER BY id DESC LIMIT ?");
        $st->bindValue(1, $limit, PDO::PARAM_INT);
        $st->execute();
        $contacts = $st->fetchAll();

        $verified = 0;
        $flagged = 0;

        $stUpdate = $db->prepare("UPDATE subscribers SET status = ? WHERE id = ?");

        foreach ($contacts as $c) {
            $res = self::verifyEmail($c['email']);
            if ($res['status'] === 'bounced' || $res['status'] === 'disposable') {
                $stUpdate->execute(['bounced', $c['id']]);
                $flagged++;
            } else {
                $verified++;
            }
        }

        return ['processed' => count($contacts), 'verified' => $verified, 'flagged' => $flagged];
    }
}
