<?php
/**
 * core/TemplateEngine.php — Email merge-tag processor
 * PHP 7.4+ compatible. Processes {{merge_tags}} in email HTML/text.
 */

class TemplateEngine
{
    /**
     * Process a campaign email body for a specific subscriber.
     *
     * Available merge tags:
     *   {{first_name}}  {{last_name}}  {{full_name}}  {{email}}
     *   {{unsubscribe_url}}  {{view_in_browser_url}}  {{subscribe_date}}
     *   {{company_name}}  {{company_address}}
     *   {{custom.KEY}}   — from subscriber's JSON attributes
     */
    public static function process(string $html, array $subscriber, int $campaignId): string
    {
        $appUrl    = getSetting('app_url', 'http://localhost');
        $company   = getSetting('company_name', getSetting('app_name', 'Merlin Spellcaster'));
        $address   = getSetting('company_address', '');

        $firstName  = $subscriber['first_name'] ?? '';
        $lastName   = $subscriber['last_name']  ?? '';
        $fullName   = trim($firstName . ' ' . $lastName) ?: $subscriber['email'];
        $email      = $subscriber['email'] ?? '';
        $subId      = (int)($subscriber['id'] ?? 0);
        $token      = generateToken($email, $campaignId, $subId);
        $subDate    = isset($subscriber['created_at'])
                      ? date('F j, Y', strtotime($subscriber['created_at']))
                      : date('F j, Y');

        $unsubUrl      = $appUrl . '/unsubscribe.php?c=' . $campaignId . '&s=' . $subId . '&t=' . $token;
        $viewUrl       = $appUrl . '/view.php?c=' . $campaignId . '&s=' . $subId . '&t=' . $token;

        // Base replacements
        $tags = [
            '{{first_name}}'           => $firstName ?: 'Subscriber',
            '{{last_name}}'            => $lastName,
            '{{full_name}}'            => $fullName,
            '{{email}}'                => $email,
            '{{unsubscribe_url}}'      => $unsubUrl,
            '{{view_in_browser_url}}'  => $viewUrl,
            '{{subscribe_date}}'       => $subDate,
            '{{company_name}}'         => $company,
            '{{company_address}}'      => $address,
            '{{campaign_id}}'          => (string)$campaignId,
            '{{subscriber_id}}'        => (string)$subId,
        ];

        // Custom attribute tags: {{custom.key}}
        $attrs = [];
        if (!empty($subscriber['attributes'])) {
            $decoded = json_decode($subscriber['attributes'], true);
            if (is_array($decoded)) {
                $attrs = $decoded;
            }
        }
        foreach ($attrs as $key => $value) {
            if (is_scalar($value)) {
                $tags['{{custom.' . $key . '}}'] = (string)$value;
            }
        }

        // Replace in HTML
        $html = str_replace(array_keys($tags), array_values($tags), $html);

        // Wrap all links for click tracking (if tracking enabled)
        if (getSetting('tracking_enabled', '1') === '1') {
            $html = self::wrapLinks($html, $campaignId, $subId, $appUrl);
        }

        return $html;
    }

    /** Add open-tracking pixel to HTML email */
    public static function addOpenPixel(string $html, int $campaignId, int $subscriberId): string
    {
        if (getSetting('tracking_enabled', '1') !== '1') return $html;

        $appUrl  = getSetting('app_url', 'http://localhost');
        $token   = generateToken(
            getSetting('smtp_from_email', ''),
            $campaignId,
            $subscriberId
        );
        $pixelUrl = $appUrl . '/o.php?c=' . $campaignId . '&s=' . $subscriberId . '&t=' . $token;
        $pixel    = '<img src="' . $pixelUrl . '" width="1" height="1" alt="" style="display:block;border:0">';

        // Insert before </body> if present, otherwise append
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $pixel . '</body>', $html);
        } else {
            $html .= $pixel;
        }

        return $html;
    }

    /** Wrap href links with click tracking URLs */
    private static function wrapLinks(string $html, int $campaignId, int $subId, string $appUrl): string
    {
        return preg_replace_callback(
            '/href=["\'](?!mailto:|tel:|#|{{unsubscribe|{{view_in_browser)([^"\']+)["\']/i',
            static function (array $m) use ($campaignId, $subId, $appUrl): string {
                $dest = $m[1];
                // Already a tracking URL? Skip
                if (strpos($dest, '/r.php') !== false) return $m[0];
                $tracked = $appUrl . '/r.php?c=' . $campaignId . '&s=' . $subId . '&dest=' . urlencode($dest);
                return 'href="' . $tracked . '"';
            },
            $html
        );
    }

    /** Render a default email template shell around content */
    public static function wrapInTemplate(string $content, string $subject): string
    {
        $company = getSetting('company_name', getSetting('app_name', 'Merlin Spellcaster'));
        $address = getSetting('company_address', '');

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</title>
<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
<style>
  body,table,td,p,a,li,blockquote{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
  table,td{mso-table-lspace:0;mso-table-rspace:0}
  img{-ms-interpolation-mode:bicubic;border:0;outline:none;text-decoration:none}
  body{margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#f1f5f9}
  .wrapper{width:100%;background:#f1f5f9;padding:40px 0}
  .container{max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)}
  .header{background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);padding:36px 40px;text-align:center}
  .header h1{color:#ffffff;margin:0;font-size:26px;font-weight:700;letter-spacing:-0.5px}
  .header p{color:rgba(255,255,255,0.75);margin:8px 0 0;font-size:14px}
  .body{padding:40px}
  .body p{color:#374151;line-height:1.75;font-size:15px;margin:0 0 16px}
  .body h1,.body h2,.body h3{color:#111827}
  .btn{display:inline-block;background:#6366f1;color:#ffffff!important;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px;margin:12px 0}
  .divider{border:none;border-top:1px solid #e5e7eb;margin:28px 0}
  .footer{background:#f9fafb;padding:28px 40px;text-align:center;border-top:1px solid #e5e7eb}
  .footer p{color:#9ca3af;font-size:13px;margin:4px 0;line-height:1.6}
  .footer a{color:#6366f1;text-decoration:none}
  @media only screen and (max-width:600px){
    .body{padding:24px!important}
    .header{padding:28px 24px!important}
    .footer{padding:20px 24px!important}
  }
</style>
</head>
<body>
<div class="wrapper">
<div class="container">
<div class="header">
  <h1>' . htmlspecialchars($company, ENT_QUOTES, 'UTF-8') . '</h1>
  <p>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</p>
</div>
<div class="body">' . $content . '</div>
<div class="footer">
  <p>' . htmlspecialchars($company, ENT_QUOTES, 'UTF-8') . (!empty($address) ? '<br>' . htmlspecialchars($address, ENT_QUOTES, 'UTF-8') : '') . '</p>
  <p><a href="{{view_in_browser_url}}">View in browser</a> &nbsp;&bull;&nbsp; <a href="{{unsubscribe_url}}">Unsubscribe</a></p>
</div>
</div>
</div>
</body>
</html>';
    }

    /** Available merge tags for UI display */
    public static function availableTags(): array
    {
        return [
            'Subscriber'  => [
                '{{first_name}}'     => 'First name',
                '{{last_name}}'      => 'Last name',
                '{{full_name}}'      => 'Full name',
                '{{email}}'          => 'Email address',
                '{{subscribe_date}}' => 'Date subscribed',
            ],
            'Links' => [
                '{{unsubscribe_url}}'     => 'Unsubscribe link',
                '{{view_in_browser_url}}' => 'View in browser link',
            ],
            'Company' => [
                '{{company_name}}'    => 'Company name',
                '{{company_address}}' => 'Company address',
            ],
            'Custom' => [
                '{{custom.KEY}}'      => 'Any subscriber attribute (replace KEY)',
            ],
        ];
    }
}
