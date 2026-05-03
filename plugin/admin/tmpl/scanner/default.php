<?php defined('_JEXEC') or die; ?>
<?php
// Extract view properties into local variables for template convenience
$baseUrl        = $this->baseUrl;
$token          = $this->token;
$pendingCount   = $this->pendingCount;
$seenCount      = $this->seenCount;
$scannedCount   = $this->scannedCount;
$progressPct    = $this->progressPct;
$summary        = $this->summary;
$criticalItems  = $this->criticalItems;
$warningItems   = $this->warningItems;
$passedItems    = $this->passedItems;
$recentItems    = $this->recentItems;
$brokenLinkItems = $this->brokenLinkItems;
$scanOptions    = $this->scanOptions;
$message        = $this->message;
?>

<style>
.le-audit-wrap {
    max-width: 1280px;
    margin: 32px auto;
    padding: 0 18px;
    font-family: Arial, Helvetica, sans-serif;
    color: #172033;
}

.le-audit-card {
    background: #ffffff;
    border: 1px solid #dfe5ef;
    border-radius: 18px;
    padding: 22px;
    margin-bottom: 18px;
    box-shadow: 0 8px 24px rgba(23, 32, 51, 0.06);
}

.le-audit-title {
    font-size: 30px;
    line-height: 1.2;
    margin: 0 0 8px;
    color: #101828;
}

.le-audit-subtitle {
    font-size: 16px;
    line-height: 1.6;
    color: #526070;
    margin: 0;
}

.le-audit-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 18px;
}

.le-audit-button {
    appearance: none;
    border: 0;
    border-radius: 999px;
    padding: 12px 18px;
    font-weight: 700;
    cursor: pointer;
    background: #1a73e8;
    color: #ffffff;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    font-size: 15px;
}

.le-audit-button:hover,
.le-audit-button:focus {
    background: #1558b0;
    color: #ffffff;
}

.le-audit-button.secondary {
    background: #eef4ff;
    color: #174ea6;
}

.le-audit-button.secondary:hover,
.le-audit-button.secondary:focus {
    background: #dbeafe;
    color: #174ea6;
}

.le-audit-button.danger {
    background: #b42318;
    color: #ffffff;
}

.le-audit-button:disabled { opacity: .6; cursor: default; }

.le-audit-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}

.le-audit-metric {
    background: #f8fafc;
    border: 1px solid #e5eaf2;
    border-radius: 14px;
    padding: 16px;
}

.le-audit-metric strong {
    display: block;
    font-size: 28px;
    color: #101828;
    margin-bottom: 4px;
}

.le-audit-metric span {
    display: block;
    color: #667085;
    font-size: 14px;
}

.le-audit-alert {
    padding: 14px 16px;
    border-radius: 14px;
    margin: 16px 0 0;
    background: #fff7e6;
    border: 1px solid #ffd591;
    color: #7a4b00;
    font-weight: 700;
    word-break: break-word;
}

.le-audit-table-wrap {
    overflow-x: auto;
    border: 1px solid #e5eaf2;
    border-radius: 14px;
}

.le-audit-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 980px;
}

.le-audit-table th,
.le-audit-table td {
    text-align: left;
    vertical-align: top;
    padding: 12px;
    border-bottom: 1px solid #e5eaf2;
    font-size: 14px;
    line-height: 1.45;
}

.le-audit-table th {
    background: #f8fafc;
    font-weight: 800;
    color: #344054;
}

.le-audit-url { max-width: 340px; word-break: break-word; }

.le-audit-pill {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
}

.le-audit-pill.critical { background: #fee4e2; color: #b42318; }
.le-audit-pill.warning  { background: #fff7e6; color: #946200; }
.le-audit-pill.pass     { background: #dcfae6; color: #067647; }

.le-audit-small { color: #667085; font-size: 13px; line-height: 1.5; }

.le-audit-code {
    background: #101828;
    color: #f9fafb;
    padding: 12px;
    border-radius: 12px;
    overflow-x: auto;
    font-size: 13px;
}

.le-audit-progress-bar-wrap {
    background: #e5eaf2;
    border-radius: 999px;
    height: 18px;
    overflow: hidden;
    margin: 10px 0 6px;
}

.le-audit-progress-bar {
    height: 18px;
    border-radius: 999px;
    background: #1a73e8;
    transition: width 0.4s ease;
}

.le-audit-progress-label {
    font-size: 14px;
    font-weight: 700;
    color: #344054;
}

@media (max-width: 900px) {
    .le-audit-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 560px) {
    .le-audit-grid    { grid-template-columns: 1fr; }
    .le-audit-title   { font-size: 24px; }
    .le-audit-button  { width: 100%; }
}
</style>

<div class="le-audit-wrap">

    <section class="le-audit-card">
        <h1 class="le-audit-title">LottoExpert Full Site Audit Scanner</h1>
        <p class="le-audit-subtitle">
            Private crawler for LottoExpert. Discovers internal URLs from the sitemap (including nested sitemap index files), crawls internal links, scans 15 URLs per batch, auto-continues without timeouts, and reports SEO, crawlability, metadata, canonical, H1, noindex, image alt, speed, and broken-page issues.
        </p>

        <div style="margin-top:18px;border:1px solid #d0d7de;border-radius:8px;padding:16px 20px;">
            <p style="margin:0 0 4px;font-weight:700;font-size:15px;color:#1a73e8;">&#9881; Scan Options</p>
            <form id="leSaveOptionsForm" method="post" action="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>" style="margin-top:10px;">
                <input type="hidden" name="audit_action" value="save_options">
                <input type="hidden" name="<?php echo $token; ?>" value="1">
                <p class="le-audit-small" style="margin:0 0 10px;">
                    Select which checks to run. Disabling checks you don&rsquo;t need makes each batch faster and prevents gateway timeouts on large sites.
                </p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:8px 24px;margin-bottom:14px;">
                    <?php
                    $opts = [
                        ['opt_broken_internal',       'check_broken_internal',       '&#128279; Broken Internal Pages',       'Flag crawled pages that return 404/5xx'],
                        ['opt_broken_internal_links', 'check_broken_internal_links', '&#128279; Internal Link Checker',        'HEAD-check internal links found on each page for broken destinations'],
                        ['opt_broken_external',       'check_broken_external',       '&#128279; Broken External Links',        'HEAD-check outbound links to other sites'],
                        ['opt_broken_images',         'check_broken_images',         '&#128279; Missing Images',               'HEAD-check image src URLs (logos, photos)'],
                        ['opt_seo',                   'check_seo',                   '&#128269; SEO Checks',                   'Title, meta description, canonical, H1, noindex'],
                        ['opt_speed',                 'check_speed',                 '&#9889; Page Speed',                     'Flag pages loading over 3 seconds'],
                        ['opt_alt_text',              'check_alt_text',              '&#9855; Alt Text',                       'Flag images missing alt attribute'],
                        ['opt_store_passed',          'store_passed_pages',          '&#9989; Store Passed Pages',             'Keep full data for pages with no issues (uses more memory)'],
                    ];
                    foreach ($opts as [$fieldName, $optKey, $label, $desc]) :
                        $checked = ($scanOptions[$optKey] ?? false) ? ' checked' : '';
                    ?>
                    <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="<?php echo $fieldName; ?>" value="1"<?php echo $checked; ?> style="margin-top:3px;flex-shrink:0;">
                        <span><strong><?php echo $label; ?></strong> <span class="le-audit-small"><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></span></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <button id="leSaveOptionsBtn" class="le-audit-button secondary" type="submit" style="font-size:14px;padding:8px 16px;">Save Options</button>
                <span id="leOptsSaved" style="display:none;margin-left:12px;font-weight:800;color:#067647;background:#dcfae6;border:1px solid #86efac;border-radius:999px;padding:7px 12px;">Saved</span>
            </form>
        </div>

        <?php if ($message !== '') : ?>
            <div class="le-audit-alert"><?php echo nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')); ?></div>
        <?php endif; ?>

        <div class="le-audit-actions">
            <form id="leAutoScanForm" method="post" action="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="audit_action" value="scan_batch">
                <input type="hidden" name="<?php echo $token; ?>" value="1">
                <button class="le-audit-button" type="submit">Scan Next 15 URLs</button>
            </form>

            <button id="leBtnAutoScan" class="le-audit-button secondary" type="button">Auto Scan Until Finished</button>

            <button id="leBtnStopScan" class="le-audit-button secondary" type="button" style="display:none;">&#9209; Stop Auto Scan</button>

            <form id="leStopAllForm" method="post" action="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>" onsubmit="return leStopAllConfirm();">
                <input type="hidden" name="audit_action" value="stop_all">
                <input type="hidden" name="<?php echo $token; ?>" value="1">
                <button class="le-audit-button danger" type="submit">&#9209; Stop All</button>
            </form>

            <form method="post" action="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="audit_action" value="discover_sitemap">
                <input type="hidden" name="<?php echo $token; ?>" value="1">
                <button class="le-audit-button secondary" type="submit">Discover Sitemap URLs</button>
            </form>

            <form method="post" action="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="audit_action" value="export_csv">
                <input type="hidden" name="<?php echo $token; ?>" value="1">
                <button class="le-audit-button secondary" type="submit" data-no-loading="1">Export CSV</button>
            </form>

            <form method="post" action="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>" onsubmit="return confirm('Reset the full audit queue and results?');">
                <input type="hidden" name="audit_action" value="reset">
                <input type="hidden" name="<?php echo $token; ?>" value="1">
                <button class="le-audit-button danger" type="submit">Reset Audit</button>
            </form>
        </div>
    </section>

    <section class="le-audit-card">
        <h2>Scan Progress</h2>
        <div class="le-audit-progress-bar-wrap">
            <div id="leProgressBar" class="le-audit-progress-bar" style="width:<?php echo (int) $progressPct; ?>%"></div>
        </div>
        <p id="leProgressLabel" class="le-audit-progress-label">
            <?php echo (int) $progressPct; ?>% complete &mdash;
            <?php echo (int) $scannedCount; ?> scanned of
            <?php echo (int) $seenCount; ?> discovered &mdash;
            <?php echo (int) $pendingCount; ?> pending &mdash;
            ETA: <span id="leProgressEta">waiting for scan speed</span>
        </p>
    </section>

    <section class="le-audit-card">
        <h2>Audit Overview</h2>
        <div class="le-audit-grid">
            <div class="le-audit-metric"><strong><?php echo (int) $scannedCount; ?></strong><span>URLs Scanned</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) $pendingCount; ?></strong><span>URLs Pending</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) $seenCount; ?></strong><span>Total Discovered</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) ($summary['critical_pages'] ?? 0); ?></strong><span>Critical Pages</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) ($summary['warning_pages'] ?? 0); ?></strong><span>Warning Pages</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) ($summary['passed_pages'] ?? 0); ?></strong><span>Passed Pages</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) ($summary['missing_titles'] ?? 0); ?></strong><span>Missing Titles</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) ($summary['missing_descriptions'] ?? 0); ?></strong><span>Missing Descriptions</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) ($summary['not_found_pages'] ?? 0); ?></strong><span>404 Pages</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) ($summary['redirect_pages'] ?? 0); ?></strong><span>Redirect Pages</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) ($summary['missing_canonicals'] ?? 0); ?></strong><span>Missing Canonicals</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) ($summary['noindex_pages'] ?? 0); ?></strong><span>Noindex Pages</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) ($summary['slow_pages'] ?? 0); ?></strong><span>Slow Pages (&gt;3 s)</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) ($summary['images_missing_alt'] ?? 0); ?></strong><span>Pages w/ Missing Alt</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) ($summary['avg_load_time_ms'] ?? 0); ?> ms</strong><span>Avg Load Time</span></div>
            <div class="le-audit-metric"><strong><?php echo (int) ($summary['broken_links_total'] ?? 0); ?></strong><span>Broken Links &amp; Missing Images</span></div>
        </div>
    </section>

    <section id="leAutorunRunning" class="le-audit-card" style="display:none">
        <h2>Auto Scan Running</h2>
        <p class="le-audit-small">
            Auto scan is active &mdash; <span id="leAutorunPending"></span> URLs still pending.
            Leave this tab open until the progress bar reaches 100%.
        </p>
    </section>

    <section id="leAutorunDone" class="le-audit-card" style="display:none">
        <h2>Auto Scan Complete</h2>
        <p class="le-audit-small">All discovered URLs have been scanned. Review the results below.</p>
    </section>

    <section class="le-audit-card">
        <h2>Recently Scanned (last 25)</h2>
        <?php if (empty($recentItems)) : ?>
            <p class="le-audit-small">No URLs scanned yet. Run a batch scan to see results here.</p>
        <?php else : ?>
            <div class="le-audit-table-wrap">
                <table class="le-audit-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>URL</th>
                            <th>Result</th>
                            <th>Load</th>
                            <th>Scanned At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentItems as $item) : ?>
                            <?php
                            if (!empty($item['issues'])) {
                                $pc = 'critical'; $pl = 'Issues';
                            } elseif (!empty($item['warnings'])) {
                                $pc = 'warning'; $pl = 'Warnings';
                            } else {
                                $pc = 'pass'; $pl = 'Pass';
                            }
                            ?>
                            <tr>
                                <td><span class="le-audit-pill <?php echo $pc; ?>"><?php echo (int) $item['status']; ?></span></td>
                                <td class="le-audit-url">
                                    <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                        <?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td><span class="le-audit-pill <?php echo $pc; ?>"><?php echo htmlspecialchars($pl, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><?php echo (int) $item['load_time_ms']; ?> ms</td>
                                <td class="le-audit-small"><?php echo htmlspecialchars($item['scanned_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($scanOptions['check_seo'] || $scanOptions['check_broken_internal'] || $scanOptions['check_speed'] || $scanOptions['check_alt_text']) : ?>
    <section class="le-audit-card">
        <h2>Critical Issues</h2>
        <?php if (empty($criticalItems)) : ?>
            <p><span class="le-audit-pill pass">PASS</span> No critical issues found yet.</p>
        <?php else : ?>
            <div class="le-audit-table-wrap">
                <table class="le-audit-table">
                    <thead>
                        <tr>
                            <th>Status</th><th>URL</th><th>Issues</th>
                            <th>Found On (source page)</th>
                            <th>Title</th><th>H1 Text</th><th>Meta Description</th>
                            <th>Canonical</th><th>Load</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($criticalItems as $item) : ?>
                            <tr>
                                <td><span class="le-audit-pill critical"><?php echo (int) $item['status']; ?></span></td>
                                <td class="le-audit-url">
                                    <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                        <?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars(implode(' | ', $item['issues']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="le-audit-url"><?php
                                    $srcPage = $item['source_page'] ?? '';
                                    if ($srcPage !== '') {
                                        echo '<a href="' . htmlspecialchars($srcPage, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . htmlspecialchars($srcPage, ENT_QUOTES, 'UTF-8') . '</a>';
                                    } else {
                                        echo '<span class="le-audit-small" style="color:#888;">&mdash;</span>';
                                    }
                                ?></td>
                                <td><?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($item['h1_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($item['meta_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="le-audit-url"><?php echo htmlspecialchars($item['canonical'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int) $item['load_time_ms']; ?> ms</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($scanOptions['check_broken_internal'] || ($scanOptions['check_broken_internal_links'] ?? false) || $scanOptions['check_broken_external'] || $scanOptions['check_broken_images']) : ?>
    <section class="le-audit-card">
        <h2>Broken Links &amp; Missing Images Found</h2>
        <p class="le-audit-small">
            These are links and images that the crawler found on your site but that returned an error when fetched.
            The <strong>Type</strong> column tells you what kind of problem it is: an internal link on your own site that is broken, a link to an external website that is returning an error, or an image file (such as a logo or photo) that could not be loaded.
            The <strong>Page On Your Site</strong> column shows exactly which page you need to edit to fix or remove the bad link or image.
            The <strong>Error Code</strong> explains what kind of failure occurred (see key below).
        </p>
        <p class="le-audit-small">
            <strong>Error code key:</strong>
            <strong>404</strong> = Not Found (the file or page no longer exists) &mdash;
            <strong>410</strong> = Gone (permanently deleted) &mdash;
            <strong>500</strong> = Server Error (the destination server crashed) &mdash;
            <strong>503</strong> = Service Unavailable (server overloaded or down) &mdash;
            <strong>0</strong> = Connection Failed (the domain could not be reached at all).
        </p>
        <?php if (empty($brokenLinkItems)) : ?>
            <p><span class="le-audit-pill pass">PASS</span> No broken links or missing images detected yet.</p>
        <?php else : ?>
            <div class="le-audit-table-wrap">
                <table class="le-audit-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Error Code</th>
                            <th>Page On Your Site (go here to fix or remove the problem)</th>
                            <th>Broken URL / Missing Image (the bad address)</th>
                            <th>Visible Text / Image Alt</th>
                            <th>Page Text Near Link</th>
                            <th>Raw HTML Snippet</th>
                            <th>When Detected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($brokenLinkItems as $bl) : ?>
                            <?php
                                $statusCode = (int) $bl['status'];
                                $statusLabel = match(true) {
                                    $statusCode === 0   => '0 &ndash; Connection Failed',
                                    $statusCode === 400 => '400 &ndash; Bad Request',
                                    $statusCode === 401 => '401 &ndash; Unauthorized',
                                    $statusCode === 403 => '403 &ndash; Forbidden',
                                    $statusCode === 404 => '404 &ndash; Not Found',
                                    $statusCode === 405 => '405 &ndash; Method Not Allowed',
                                    $statusCode === 410 => '410 &ndash; Gone (Deleted)',
                                    $statusCode === 429 => '429 &ndash; Too Many Requests',
                                    $statusCode === 500 => '500 &ndash; Server Error',
                                    $statusCode === 502 => '502 &ndash; Bad Gateway',
                                    $statusCode === 503 => '503 &ndash; Service Unavailable',
                                    $statusCode === 504 => '504 &ndash; Gateway Timeout',
                                    $statusCode >= 400 && $statusCode < 500 => $statusCode . ' &ndash; Client Error',
                                    $statusCode >= 500 => $statusCode . ' &ndash; Server Error',
                                    default             => (string) $statusCode,
                                };
                                $foundAt = '';
                                if (!empty($bl['found_at'])) {
                                    $ts = strtotime($bl['found_at']);
                                    $foundAt = $ts ? date('d M Y, H:i', $ts) . ' UTC' : $bl['found_at'];
                                }
                                $linkType = $bl['link_type'] ?? 'internal';
                                $typeLabel = match($linkType) {
                                    'image'    => '&#128248; Missing Image',
                                    'external' => '&#127760; External Link',
                                    default    => '&#128279; Internal Link',
                                };
                            ?>
                            <tr>
                                <td><?php echo $typeLabel; ?></td>
                                <td><span class="le-audit-pill critical"><?php echo $statusLabel; ?></span></td>
                                <td class="le-audit-url">
                                    <a href="<?php echo htmlspecialchars($bl['source_page'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                        <?php echo htmlspecialchars($bl['source_page'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td class="le-audit-url">
                                    <a href="<?php echo htmlspecialchars($bl['broken_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                        <?php echo htmlspecialchars($bl['broken_url'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($bl['link_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="le-audit-url"><?php echo htmlspecialchars($bl['source_excerpt'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="le-audit-url"><code><?php echo htmlspecialchars($bl['html_snippet'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td class="le-audit-small"><?php echo htmlspecialchars($foundAt, ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($scanOptions['check_seo'] || $scanOptions['check_speed'] || $scanOptions['check_alt_text']) : ?>
    <section class="le-audit-card">
        <h2>Warnings</h2>
        <p class="le-audit-small">
            These pages loaded successfully but have one or more SEO or performance issues that should be fixed. Each warning below explains exactly what the problem is and what you need to do to correct it.
        </p>
        <?php if (empty($warningItems)) : ?>
            <p><span class="le-audit-pill pass">PASS</span> No warnings found yet.</p>
        <?php else : ?>
            <div class="le-audit-table-wrap">
                <table class="le-audit-table">
                    <thead>
                        <tr>
                            <th>Status</th><th>Page URL</th><th>Warning Details (what is wrong and how to fix it)</th>
                            <th>Title Length</th><th>Desc. Length</th>
                            <th>H1 Count</th><th>Imgs No Alt</th><th>Load</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($warningItems as $item) : ?>
                            <tr>
                                <td><span class="le-audit-pill warning"><?php echo (int) $item['status']; ?></span></td>
                                <td class="le-audit-url">
                                    <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                        <?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td>
                                    <ul style="margin:0;padding-left:18px;">
                                        <?php foreach ($item['warnings'] as $w) : ?>
                                            <li><?php echo htmlspecialchars($w, ENT_QUOTES, 'UTF-8'); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                                <td><?php echo (int) $item['title_length']; ?></td>
                                <td><?php echo (int) $item['meta_description_length']; ?></td>
                                <td><?php echo (int) $item['h1_count']; ?></td>
                                <td><?php echo (int) ($item['images_without_alt'] ?? 0); ?></td>
                                <td><?php echo (int) $item['load_time_ms']; ?> ms</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($scanOptions['store_passed_pages'] ?? false) : ?>
    <section class="le-audit-card">
        <h2>Passed Pages (no issues, no warnings) &mdash; showing up to 100</h2>
        <?php if (empty($passedItems)) : ?>
            <p class="le-audit-small">No passed pages recorded yet.</p>
        <?php else : ?>
            <div class="le-audit-table-wrap">
                <table class="le-audit-table">
                    <thead>
                        <tr>
                            <th>Status</th><th>URL</th><th>Title</th><th>Load</th><th>Scanned At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($passedItems as $item) : ?>
                            <tr>
                                <td><span class="le-audit-pill pass"><?php echo (int) $item['status']; ?></span></td>
                                <td class="le-audit-url">
                                    <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                        <?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int) $item['load_time_ms']; ?> ms</td>
                                <td class="le-audit-small"><?php echo htmlspecialchars($item['scanned_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="le-audit-card">
        <h2>CSV Export</h2>
        <p class="le-audit-small">
            Use the &ldquo;Export CSV&rdquo; button above to download all scanned results as a CSV file directly to your computer.
        </p>
    </section>

</div>

<div id="leAuditMeta" data-pending="<?php echo (int) $pendingCount; ?>" style="display:none"></div>
<script>
(function () {
    var AUTORUN_KEY = 'leAuditAutorun';
    var SCROLL_KEY  = 'leAuditScrollY';
    var LS_OPT_KEY  = 'leAuditScanOptions';
    var meta    = document.getElementById('leAuditMeta');
    var pending = meta ? parseInt(meta.getAttribute('data-pending') || '0', 10) : 0;

    var leOptMap = {
        'opt_broken_internal':       'check_broken_internal',
        'opt_broken_internal_links': 'check_broken_internal_links',
        'opt_broken_external':       'check_broken_external',
        'opt_broken_images':         'check_broken_images',
        'opt_seo':                   'check_seo',
        'opt_speed':                 'check_speed',
        'opt_alt_text':              'check_alt_text',
        'opt_store_passed':          'store_passed_pages'
    };

    function leGetOpts() {
        var opts = {};
        Object.keys(leOptMap).forEach(function (fn) {
            var el = document.querySelector('input[name="' + fn + '"]');
            opts[leOptMap[fn]] = el ? el.checked : true;
        });
        return opts;
    }

    function leApplyOpts(opts) {
        Object.keys(leOptMap).forEach(function (fn) {
            var key = leOptMap[fn];
            var el  = document.querySelector('input[name="' + fn + '"]');
            if (el && opts.hasOwnProperty(key)) { el.checked = !!opts[key]; }
        });
    }

    function leInjectBatchOpts(form, opts) {
        form.querySelectorAll('input[data-le-batch-opt]').forEach(function (el) {
            el.parentNode.removeChild(el);
        });
        Object.keys(opts).forEach(function (k) {
            var inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = 'batch_opt_' + k;
            inp.value = opts[k] ? '1' : '0';
            inp.setAttribute('data-le-batch-opt', '1');
            form.appendChild(inp);
        });
    }

    function leLoadOpts() {
        try {
            var raw = localStorage.getItem(LS_OPT_KEY);
            return raw ? JSON.parse(raw) : leGetOpts();
        } catch (e) { return leGetOpts(); }
    }

    function leSubmitBatchForm(form) {
        leInjectBatchOpts(form, leLoadOpts());
        form.submit();
    }

    try {
        var stored = localStorage.getItem(LS_OPT_KEY);
        if (stored) { leApplyOpts(JSON.parse(stored)); }
    } catch (e) {}

    var saveForm = document.getElementById('leSaveOptionsForm');
    var saveBtn  = document.getElementById('leSaveOptionsBtn');
    if (saveForm) {
        saveForm.addEventListener('submit', function (ev) {
            ev.preventDefault();

            var opts = leGetOpts();
            try { localStorage.setItem(LS_OPT_KEY, JSON.stringify(opts)); } catch (e) {}

            var msg = document.getElementById('leOptsSaved');
            var fd  = new FormData(saveForm);
            fd.set('audit_action', 'save_options_ajax');

            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = 'Saving...';
            }
            if (msg) {
                msg.style.display = 'inline-block';
                msg.style.color = '#946200';
                msg.style.background = '#fff7e6';
                msg.style.borderColor = '#ffd591';
                msg.innerHTML = 'Saving...';
            }

            fetch(saveForm.action, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) {
                    if (!r.ok) { throw new Error('HTTP ' + r.status); }
                    return r.json();
                })
                .then(function (data) {
                    if (!data || !data.ok) { throw new Error('Save failed'); }
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = 'Saved';
                        saveBtn.style.background = '#067647';
                        saveBtn.style.color = '#ffffff';
                    }
                    if (msg) {
                        msg.style.display = 'inline-block';
                        msg.style.color = '#067647';
                        msg.style.background = '#dcfae6';
                        msg.style.borderColor = '#86efac';
                        msg.innerHTML = 'Saved';
                    }
                    setTimeout(function () {
                        if (saveBtn) {
                            saveBtn.innerHTML = 'Save Options';
                            saveBtn.style.background = '';
                            saveBtn.style.color = '';
                        }
                    }, 3000);
                })
                .catch(function () {
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = 'Save Options';
                    }
                    if (msg) {
                        msg.style.display = 'inline-block';
                        msg.style.color = '#b42318';
                        msg.style.background = '#fee4e2';
                        msg.style.borderColor = '#fecdca';
                        msg.innerHTML = 'Save failed. Refresh and try again.';
                    }
                });
        });
    }

    var batchForm = document.getElementById('leAutoScanForm');
    if (batchForm) {
        batchForm.addEventListener('submit', function () {
            leInjectBatchOpts(this, leLoadOpts());
        });
    }

    var buttons = document.querySelectorAll('.le-audit-button');
    for (var i = 0; i < buttons.length; i++) {
        buttons[i].addEventListener('click', function () {
            if (!this.className.match(/danger/) && this.id !== 'leBtnAutoScan' && this.id !== 'leBtnStopScan' && this.id !== 'leSaveOptionsBtn' && this.getAttribute('data-no-loading') !== '1') {
                this.innerHTML = 'Working...';
                this.disabled = true;
            }
        });
    }

    function leUpdateProgress(pct, scanned, seen, pendingN, etaLabel) {
        var bar   = document.getElementById('leProgressBar');
        var label = document.getElementById('leProgressLabel');
        if (bar)   { bar.style.width = pct + '%'; }
        if (label) {
            label.innerHTML = pct + '% complete &mdash; '
                + scanned + ' scanned of '
                + seen    + ' discovered &mdash; '
                + pendingN + ' pending &mdash; ETA: <span id="leProgressEta">'
                + (etaLabel || 'calculating') + '</span>';
        }
    }

    var runningBanner = document.getElementById('leAutorunRunning');
    var doneBanner    = document.getElementById('leAutorunDone');
    var pendingSpan   = document.getElementById('leAutorunPending');

    function leRunAjaxBatch() {
        if (sessionStorage.getItem(AUTORUN_KEY) !== '1') { return; }
        var ajaxForm = document.getElementById('leAutoScanForm');
        if (!ajaxForm) { return; }
        var opts = leLoadOpts();
        var fd   = new FormData(ajaxForm);
        fd.set('audit_action', 'scan_batch_ajax');
        Object.keys(opts).forEach(function (k) {
            fd.set('batch_opt_' + k, opts[k] ? '1' : '0');
        });
        fetch(ajaxForm.action, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.json();
            })
            .then(function (data) {
                leUpdateProgress(data.progress_pct, data.scanned, data.seen, data.pending, data.eta_label);
                if (pendingSpan) { pendingSpan.textContent = data.pending; }
                if (sessionStorage.getItem(AUTORUN_KEY) !== '1') { return; }
                if (data.done || data.pending <= 0) {
                    sessionStorage.removeItem(AUTORUN_KEY);
                    sessionStorage.removeItem(SCROLL_KEY);
                    window.location.reload();
                    return;
                }
                setTimeout(leRunAjaxBatch, 100);
            })
            .catch(function () {
                if (sessionStorage.getItem(AUTORUN_KEY) === '1') {
                    setTimeout(leRunAjaxBatch, 3000);
                }
            });
    }

    var autoBtn = document.getElementById('leBtnAutoScan');
    if (autoBtn) {
        autoBtn.addEventListener('click', function () {
            sessionStorage.setItem(AUTORUN_KEY, '1');
            this.innerHTML = 'Auto Scan Running\u2026';
            this.disabled = true;
            var sb = document.getElementById('leBtnStopScan');
            if (sb) { sb.style.display = ''; }
            if (runningBanner) {
                runningBanner.style.display = '';
                if (pendingSpan) { pendingSpan.textContent = pending; }
            }
            leRunAjaxBatch();
        });
    }

    var stopBtn = document.getElementById('leBtnStopScan');
    if (stopBtn) {
        stopBtn.addEventListener('click', function () {
            sessionStorage.removeItem(AUTORUN_KEY);
            sessionStorage.removeItem(SCROLL_KEY);
            this.style.display = 'none';
            var running = document.getElementById('leAutorunRunning');
            if (running) { running.style.display = 'none'; }
            var ab = document.getElementById('leBtnAutoScan');
            if (ab) { ab.innerHTML = 'Auto Scan Until Finished'; ab.disabled = false; }
        });
    }

    window.leStopAllConfirm = function () {
        sessionStorage.removeItem(AUTORUN_KEY);
        sessionStorage.removeItem(SCROLL_KEY);
        return confirm('Stop all scanning and clear the pending queue? Results collected so far will be kept.');
    };

    if (sessionStorage.getItem(AUTORUN_KEY) === '1') {
        var savedY = parseInt(sessionStorage.getItem(SCROLL_KEY) || '0', 10);
        if (savedY > 0) { window.scrollTo(0, savedY); }
        if (pending > 0) {
            if (stopBtn)  { stopBtn.style.display = ''; }
            if (autoBtn)  { autoBtn.innerHTML = 'Auto Scan Running\u2026'; autoBtn.disabled = true; }
            if (runningBanner) {
                runningBanner.style.display = '';
                if (pendingSpan) { pendingSpan.textContent = pending; }
            }
            setTimeout(leRunAjaxBatch, 1500);
        } else {
            sessionStorage.removeItem(AUTORUN_KEY);
            sessionStorage.removeItem(SCROLL_KEY);
            if (doneBanner) { doneBanner.style.display = ''; }
        }
    }
})();
</script>
