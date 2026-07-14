<?php
declare(strict_types=1);

// Hook into campaign builder after subject field to show the copyable embed tag
Hook::register('campaign_form_after_subject', function() {
    $baseUrl = rtrim(getSetting('app_url'), '/');
    $sampleDate = date('Y-m-d', strtotime('+3 days')) . 'T23:59:59';
    $embedUrl = $baseUrl . '/fomo/render?end=' . urlencode($sampleDate);
    ?>
    <div class="form-group" style="margin-top: 16px; background-color: rgba(255, 91, 96, 0.05); border: 1px dashed rgba(255, 91, 96, 0.2); border-radius: 8px; padding: 16px;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <span style="font-size: 16px;">⏰</span>
            <strong style="font-size: 13px; color: var(--stripe-dark);">Dynamic Countdown Timer (FOMO)</strong>
        </div>
        <p style="font-size: 12px; color: var(--stripe-dark-slate); line-height: 1.5; margin-bottom: 8px;">
            Drive urgency by embedding a live countdown clock in your campaign email body. Copy the HTML code below and insert it into your HTML body where you want the clock to display:
        </p>
        <input class="form-control" type="text" readonly value='<img src="<?= e($embedUrl) ?>" alt="Countdown" width="600" style="display:block; max-width:100%; border-radius:8px;">' onclick="this.select()" style="font-family: monospace; font-size: 12px; margin-bottom: 0; background-color: white;">
        <span style="font-size: 11px; color: var(--stripe-dark-slate); margin-top: 4px; display: block;">You can change the <code>end</code> URL parameter date value to adjust your target deadline.</span>
    </div>
    <?php
});

// Dynamic Route: Render Countdown PNG/GIF
if ($routePath === '/fomo/render') {
    $endStr = $_GET['end'] ?? date('Y-m-d\T23:59:59');
    $endTime = strtotime($endStr);
    $now = time();
    $diff = $endTime - $now;

    if ($diff < 0) $diff = 0;

    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $seconds = $diff % 60;

    $text = sprintf("%02d DAYS  %02d HRS  %02d MINS  %02d SECS", $days, $hours, $minutes, $seconds);
    if ($diff === 0) {
        $text = "OFFER EXPIRED";
    }

    $width = 600;
    $height = 100;

    // Check if GD is enabled
    if (function_exists('imagecreatetruecolor')) {
        $image = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($image, 11, 15, 25); // #0b0f19 (dark slate background)
        $text_color = imagecolorallocate($image, 255, 91, 96); // #ff5b60 (Merlin theme rose)

        imagefill($image, 0, 0, $bg);

        // Built-in large font
        $font = 5;
        $fw = imagefontwidth($font) * strlen($text);
        $fh = imagefontheight($font);

        // Center text
        $x = (int)(($width - $fw) / 2);
        $y = (int)(($height - $fh) / 2);

        imagestring($image, $font, $x, $y, $text, $text_color);

        header('Content-Type: image/png');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        imagepng($image);
        imagedestroy($image);
    } else {
        // Fallback to text output if GD is missing
        header('Content-Type: text/plain');
        echo $text;
    }
    exit;
}
