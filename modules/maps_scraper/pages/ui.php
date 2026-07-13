<?php
declare(strict_types=1);
$pageTitle = 'Google Maps B2B Scraper';
require_once dirname(__DIR__, 3) . '/includes/header.php';
?>

<div class="flex items-center gap-4 mb-8">
  <h1 class="text-2xl font-bold text-white">Google Maps B2B Lead Scraper</h1>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
  <div class="card p-6 border-t-4 border-indigo-500">
    <h2 class="text-lg font-bold text-white mb-4">Start Local Scrape</h2>
    <form method="post" onsubmit="alert('This feature requires an Outscraper or SerpApi key to prevent IP bans. Add your API key in Settings to activate the Maps engine.'); return false;">
      <div class="mb-4">
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Niche / Business Type</label>
        <input type="text" name="niche" class="form-input w-full" placeholder="e.g. 'Plumbers', 'Dentists'" required>
      </div>
      <div class="mb-6">
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">City / Location</label>
        <input type="text" name="location" class="form-input w-full" placeholder="e.g. 'Chicago, IL'" required>
      </div>
      <button type="submit" class="btn btn-primary w-full justify-center">Start Scraping</button>
    </form>
  </div>
  
  <div class="card p-6 bg-indigo-500/5 border border-indigo-500/20">
      <h3 class="font-bold text-indigo-400 mb-2">How it works</h3>
      <p class="text-slate-300 text-sm mb-4 leading-relaxed">
          The Maps Scraper connects to Google Maps to find every business matching your niche in the target city. It then crawls each business's website to extract their public contact email addresses.
      </p>
      <ul class="text-sm text-slate-400 space-y-2">
          <li class="flex items-center gap-2">✓ Bypasses manual lead generation</li>
          <li class="flex items-center gap-2">✓ Extracts highly targeted B2B emails</li>
          <li class="flex items-center gap-2">✓ Feeds directly into your lists</li>
      </ul>
  </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
