<?php
/**
 * com_leaudit — controller.
 *
 * Contains all leAudit* helper functions and the LeauditController class
 * whose display() task handles every "audit_action" POST/GET request.
 *
 * To add a new scan option or export format, add a helper function below
 * and a new elseif branch inside LeauditController::display().
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

// ============================================================================
// Helper functions
// (All leAudit* functions are ported from the Sourcerer article; the only
//  change is that session storage now goes through the Joomla session API.)
// ============================================================================

function leAuditGetDefaultOptions(): array
{
    return [
        'check_broken_internal'       => true,
        'check_broken_internal_links' => true,
        'check_broken_external'       => true,
        'check_broken_images'         => true,
        'check_seo'                   => true,
        'check_speed'                 => true,
        'check_alt_text'              => true,
        'store_passed_pages'          => false,
    ];
}

function leAuditNormalizeUrl(string $url, array $config): string
{
    $url = html_entity_decode(trim($url), ENT_QUOTES, 'UTF-8');

    if ($url === '') {
        return '';
    }

    foreach ($config['ignore_patterns'] as $pattern) {
        if (stripos($url, $pattern) !== false) {
            return '';
        }
    }

    if (strpos($url, '//') === 0) {
        $url = 'https:' . $url;
    }

    if (strpos($url, '/') === 0) {
        $url = rtrim($config['site_root'], '/') . $url;
    }

    if (!preg_match('#^https?://#i', $url)) {
        return '';
    }

    $parts = parse_url($url);

    if (empty($parts['host'])) {
        return '';
    }

    $host = strtolower($parts['host']);

    if (!in_array($host, $config['allowed_hosts'], true)) {
        return '';
    }

    $scheme = 'https';
    $path   = $parts['path'] ?? '/';
    $query  = $parts['query'] ?? '';

    $path = preg_replace('#/+#', '/', $path);

    $clean = $scheme . '://' . $config['allowed_host'] . $path;

    if ($query !== '') {
        parse_str($query, $queryArray);

        $blockedQueryKeys = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'fbclid', 'gclid', 'msclkid', 'print', 'tmpl', 'format',
        ];

        foreach ($blockedQueryKeys as $blockedKey) {
            unset($queryArray[$blockedKey]);
        }

        if (!empty($queryArray)) {
            ksort($queryArray);
            $clean .= '?' . http_build_query($queryArray);
        }
    }

    $root = 'https://' . $config['allowed_host'];
    return rtrim($clean, '/') === $root ? $root . '/' : rtrim($clean, '/');
}

function leAuditSessionGet(string $key, array $default): array
{
    $val = Factory::getApplication()->getSession()->get($key, null);
    return is_array($val) ? $val : $default;
}

function leAuditSessionSet(string $key, array $data): void
{
    Factory::getApplication()->getSession()->set($key, $data);
}

function leAuditInitState(array $config): array
{
    $queue   = leAuditSessionGet('le_queue', []);
    $results = leAuditSessionGet('le_results', []);

    if (empty($queue)) {
        $home  = leAuditNormalizeUrl($config['site_root'] . '/', $config);
        $queue = [
            'created_at'         => gmdate('c'),
            'updated_at'         => gmdate('c'),
            'pending'            => [$home],
            'seen'               => [$home => true],
            'scanned'            => [],
            'referrers'          => [],
            'ext_pending'        => [],
            'ext_seen'           => [],
            'ext_referrers'      => [],
            'link_contexts'      => [],
            'ext_contexts'       => [],
            'int_link_pending'   => [],
            'int_link_seen'      => [],
            'int_link_referrers' => [],
            'int_link_contexts'  => [],
        ];
        leAuditSessionSet('le_queue', $queue);
    }

    if (empty($results)) {
        $results = [
            'created_at'   => gmdate('c'),
            'updated_at'   => gmdate('c'),
            'items'        => [],
            'summary'      => [],
            'broken_links' => [],
        ];
        leAuditSessionSet('le_results', $results);
    }

    return [$queue, $results];
}

function leAuditFetchUrl(string $url, array $config, ?int $timeout = null): array
{
    $timeoutSeconds = $timeout ?? (int) $config['request_timeout'];
    $response = [
        'url'              => $url,
        'status'           => 0,
        'final_url'        => $url,
        'content_type'     => '',
        'body'             => '',
        'error'            => '',
        'load_time_ms'     => 0,
        'response_headers' => '',
    ];

    $start = microtime(true);

    if (!function_exists('curl_init')) {
        $response['error'] = 'cURL is not available on this server.';
        return $response;
    }

    $ch               = curl_init();
    $headersCollected = '';

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
        CURLOPT_TIMEOUT        => $timeoutSeconds,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'LottoExpert Private Audit Scanner/2.0',
        CURLOPT_HEADER         => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$headersCollected) {
            $headersCollected .= $header;
            return strlen($header);
        },
    ]);

    $body = curl_exec($ch);

    if ($body === false) {
        $response['error'] = curl_error($ch);
    } else {
        $response['body'] = (string) $body;
    }

    $response['status']           = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response['final_url']        = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $response['content_type']     = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $response['load_time_ms']     = (int) round((microtime(true) - $start) * 1000);
    $response['response_headers'] = $headersCollected;

    curl_close($ch);
    return $response;
}

function leAuditExtractTag(string $html, string $tag): string
{
    if (preg_match('#<' . preg_quote($tag, '#') . '[^>]*>(.*?)</' . preg_quote($tag, '#') . '>#is', $html, $m)) {
        return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
    }
    return '';
}

function leAuditExtractMetaDescription(string $html): string
{
    if (preg_match('#<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>#i', $html, $m)) {
        return trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
    }
    if (preg_match('#<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\'][^>]*>#i', $html, $m)) {
        return trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
    }
    return '';
}

function leAuditExtractCanonical(string $html): string
{
    if (preg_match('#<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']*)["\'][^>]*>#i', $html, $m)) {
        return trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
    }
    if (preg_match('#<link[^>]+href=["\']([^"\']*)["\'][^>]+rel=["\']canonical["\'][^>]*>#i', $html, $m)) {
        return trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
    }
    return '';
}

function leAuditCompactText(string $text, int $maxLength = 280): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim((string) $text);

    if ($text === '') {
        return '';
    }

    if (mb_strlen($text, 'UTF-8') <= $maxLength) {
        return $text;
    }

    return mb_substr($text, 0, $maxLength - 3, 'UTF-8') . '...';
}

function leAuditExtractAttr(string $tag, string $attr): string
{
    if (preg_match('#\b' . preg_quote($attr, '#') . '\s*=\s*["\']([^"\']*)["\']#i', $tag, $m)) {
        return trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
    }
    return '';
}

function leAuditBuildSourceContext(string $html, int $offset, int $length): array
{
    $beforeStart = max(0, $offset - 300);
    $afterEnd    = min(strlen($html), $offset + $length + 300);
    $rawContext  = substr($html, $beforeStart, $afterEnd - $beforeStart);
    $rawSnippet  = substr($html, $offset, $length);

    return [
        'source_excerpt' => leAuditCompactText($rawContext, 420),
        'html_snippet'   => leAuditCompactText($rawSnippet, 420),
    ];
}

function leAuditNormalizeExternalUrlForReport(string $url, array $config): string
{
    $url = html_entity_decode(trim($url), ENT_QUOTES, 'UTF-8');

    if ($url === '') {
        return '';
    }

    if (strpos($url, '//') === 0) {
        $url = 'https:' . $url;
    }

    if (!preg_match('#^https?://#i', $url)) {
        return '';
    }

    $parts = parse_url($url);

    if (empty($parts['host'])) {
        return '';
    }

    $host = strtolower($parts['host']);

    if (in_array($host, $config['allowed_hosts'], true)) {
        return '';
    }

    $scheme = $parts['scheme'] ?? 'https';
    $clean  = $scheme . '://' . $parts['host'] . ($parts['path'] ?? '/');

    if (!empty($parts['query'])) {
        $clean .= '?' . $parts['query'];
    }

    return $clean;
}

function leAuditResolveImageUrl(string $src, string $baseUrl, array $config): string
{
    $src = html_entity_decode(trim($src), ENT_QUOTES, 'UTF-8');

    if ($src === '' || strpos($src, 'data:') === 0) {
        return '';
    }

    if (strpos($src, '//') === 0) {
        $src = 'https:' . $src;
    } elseif (strpos($src, '/') === 0) {
        $src = rtrim($config['site_root'], '/') . $src;
    } elseif (!preg_match('#^https?://#i', $src)) {
        $baseParts = parse_url($baseUrl);
        $baseRoot  = 'https://' . ($baseParts['host'] ?? $config['allowed_host']);
        $basePath  = isset($baseParts['path']) ? dirname($baseParts['path']) : '';
        $src       = rtrim($baseRoot . '/' . trim($basePath, '/'), '/') . '/' . ltrim($src, '/');
    }

    return preg_match('#^https?://#i', $src) ? $src : '';
}

function leAuditExtractLinkContexts(string $html, string $baseUrl, array $config): array
{
    $contexts = [];

    if (!preg_match_all('#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return $contexts;
    }

    foreach ($matches as $match) {
        $tag      = $match[0][0];
        $offset   = (int) $match[0][1];
        $href     = $match[1][0];
        $inner    = $match[2][0] ?? '';
        $internal = leAuditNormalizeUrl($href, $config);
        $external = $internal === '' ? leAuditNormalizeExternalUrlForReport($href, $config) : '';
        $key      = $internal !== '' ? $internal : $external;

        if ($key === '') {
            continue;
        }

        if (isset($contexts[$key])) {
            continue;
        }

        $context              = leAuditBuildSourceContext($html, $offset, strlen($tag));
        $context['link_text'] = leAuditCompactText($inner, 220);
        $context['raw_href']  = html_entity_decode(trim($href), ENT_QUOTES, 'UTF-8');
        $contexts[$key]       = $context;
    }

    return $contexts;
}

function leAuditExtractImageContexts(string $html, string $baseUrl, array $config): array
{
    $contexts = [];

    if (!preg_match_all('#<img\s[^>]*src=["\']([^"\']+)["\'][^>]*>#i', $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return $contexts;
    }

    foreach ($matches as $match) {
        $tag    = $match[0][0];
        $offset = (int) $match[0][1];
        $src    = $match[1][0];
        $url    = leAuditResolveImageUrl($src, $baseUrl, $config);

        if ($url === '' || isset($contexts[$url])) {
            continue;
        }

        $context              = leAuditBuildSourceContext($html, $offset, strlen($tag));
        $alt                  = leAuditExtractAttr($tag, 'alt');
        $context['link_text'] = $alt !== '' ? 'Image alt: ' . leAuditCompactText($alt, 180) : 'Image tag with no alt text';
        $context['raw_href']  = html_entity_decode(trim($src), ENT_QUOTES, 'UTF-8');
        $contexts[$url]       = $context;
    }

    return $contexts;
}

function leAuditExtractLinks(string $html, string $baseUrl, array $config): array
{
    $links    = [];
    $contexts = leAuditExtractLinkContexts($html, $baseUrl, $config);

    foreach (array_keys($contexts) as $url) {
        $normalized = leAuditNormalizeUrl($url, $config);
        if ($normalized !== '') {
            $links[] = $normalized;
        }
    }

    return array_values(array_unique($links));
}

function leAuditExtractExternalLinks(string $html, array $config): array
{
    $links = [];
    if (!preg_match_all('#<a\s[^>]*href=["\']([^"\']+)["\']#i', $html, $m)) {
        return $links;
    }
    foreach ($m[1] as $href) {
        $href = html_entity_decode(trim($href), ENT_QUOTES, 'UTF-8');
        if ($href === '') {
            continue;
        }
        if (strpos($href, '//') === 0) {
            $href = 'https:' . $href;
        }
        if (!preg_match('#^https?://#i', $href)) {
            continue;
        }
        $parts = parse_url($href);
        if (empty($parts['host'])) {
            continue;
        }
        $host = strtolower($parts['host']);
        if (in_array($host, $config['allowed_hosts'], true)) {
            continue;
        }
        $clean = $parts['scheme'] . '://' . $parts['host'] . ($parts['path'] ?? '/');
        if (!empty($parts['query'])) {
            $clean .= '?' . $parts['query'];
        }
        $links[] = $clean;
        if (count($links) >= 50) {
            break;
        }
    }
    return array_values(array_unique($links));
}

function leAuditHeadCheckUrl(string $url): int
{
    if (!function_exists('curl_init')) {
        return 0;
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT      => 'LottoExpert Private Audit Scanner/2.0',
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status;
}

function leAuditExtractImages(string $html, string $baseUrl, array $config): array
{
    $images = [];
    if (!preg_match_all('#<img\s[^>]*src=["\']([^"\']+)["\']#i', $html, $m)) {
        return $images;
    }
    foreach ($m[1] as $src) {
        $src = html_entity_decode(trim($src), ENT_QUOTES, 'UTF-8');
        if ($src === '' || strpos($src, 'data:') === 0) {
            continue;
        }
        $src = leAuditResolveImageUrl($src, $baseUrl, $config);
        if ($src === '') {
            continue;
        }
        $images[] = $src;
        if (count($images) >= 30) {
            break;
        }
    }
    return array_values(array_unique($images));
}

function leAuditCountImagesWithoutAlt(string $html): int
{
    $count = 0;
    if (preg_match_all('#<img\s[^>]*>#i', $html, $m)) {
        foreach ($m[0] as $tag) {
            if (!preg_match('#\balt\s*=#i', $tag)) {
                $count++;
            }
        }
    }
    return $count;
}

function leAuditCountH1(string $html): int
{
    return preg_match_all('#<h1\b[^>]*>(.*?)</h1>#is', $html) ?: 0;
}

function leAuditExtractH1Text(string $html): string
{
    if (preg_match('#<h1\b[^>]*>(.*?)</h1>#is', $html, $m)) {
        return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
    }
    return '';
}

function leAuditHasNoindex(string $html, string $responseHeaders = ''): bool
{
    if (preg_match('#<meta[^>]+name=["\']robots["\'][^>]+content=["\'][^"\']*noindex[^"\']*["\']#i', $html)) {
        return true;
    }
    if ($responseHeaders !== '' && preg_match('#x-robots-tag:[^\r\n]*noindex#i', $responseHeaders)) {
        return true;
    }
    return false;
}

function leAuditCheckUrl(string $url, array $config, array $options = []): array
{
    $checkSeo     = $options['check_seo']      ?? true;
    $checkSpeed   = $options['check_speed']    ?? true;
    $checkAltText = $options['check_alt_text'] ?? true;

    $fetch = leAuditFetchUrl($url, $config);
    $html  = $fetch['body'];

    $title       = $checkSeo     ? leAuditExtractTag($html, 'title')                    : '';
    $description = $checkSeo     ? leAuditExtractMetaDescription($html)                  : '';
    $canonical   = $checkSeo     ? leAuditExtractCanonical($html)                        : '';
    $h1Count     = $checkSeo     ? leAuditCountH1($html)                                 : 0;
    $h1Text      = $checkSeo     ? leAuditExtractH1Text($html)                           : '';
    $noindex     = $checkSeo     ? leAuditHasNoindex($html, $fetch['response_headers'])   : false;
    $imgsNoAlt   = $checkAltText ? leAuditCountImagesWithoutAlt($html)                   : 0;

    $titleLen = mb_strlen($title, 'UTF-8');
    $descLen  = mb_strlen($description, 'UTF-8');

    $issues   = [];
    $warnings = [];

    if ($fetch['status'] === 0) {
        $issues[] = 'Connection failed: ' . $fetch['error'];
    } elseif ($fetch['status'] === 404) {
        $issues[] = '404 Not Found';
    } elseif ($fetch['status'] >= 500) {
        $issues[] = 'Server error ' . $fetch['status'];
    } elseif ($fetch['status'] >= 400) {
        $issues[] = 'Client error ' . $fetch['status'];
    }

    if ($checkSeo && $fetch['status'] >= 200 && $fetch['status'] < 300) {
        if ($title === '') {
            $issues[] = 'Missing title tag';
        } elseif ($titleLen < 10) {
            $warnings[] = 'Page title is too short (' . $titleLen . ' chars) — Google recommends 50–70 characters.';
        } elseif ($titleLen > 70) {
            $warnings[] = 'Page title is too long (' . $titleLen . ' chars) — Google truncates titles over 70 characters.';
        }

        if ($description === '') {
            $issues[] = 'Missing meta description';
        } elseif ($descLen < 50) {
            $warnings[] = 'Meta description is too short (' . $descLen . ' chars) — Aim for 50–165 characters.';
        } elseif ($descLen > 165) {
            $warnings[] = 'Meta description is too long (' . $descLen . ' chars) — Google truncates over ~165 characters.';
        }

        if ($canonical === '') {
            $warnings[] = 'Missing canonical tag — Add a self-referencing canonical to the <head>.';
        }

        if ($h1Count === 0) {
            $issues[] = 'Missing H1 tag';
        } elseif ($h1Count > 1) {
            $warnings[] = 'Multiple H1 headings found (' . $h1Count . ') — Each page should have exactly one H1.';
        }

        if ($noindex) {
            $warnings[] = 'Page has a noindex directive — This page will not appear in search results.';
        }
    }

    if ($checkAltText && $imgsNoAlt > 0 && $fetch['status'] >= 200 && $fetch['status'] < 300) {
        $warnings[] = $imgsNoAlt . ' image(s) missing alt text — Add descriptive alt attributes for SEO and accessibility.';
    }

    if ($checkSpeed && $fetch['load_time_ms'] > 3000 && $fetch['status'] >= 200 && $fetch['status'] < 300) {
        $warnings[] = 'Slow page load time (' . $fetch['load_time_ms'] . ' ms) — Pages over 3 s are penalised by Google.';
    }

    $discoveredLinks         = leAuditExtractLinks($html, $url, $config);
    $discoveredExternalLinks = leAuditExtractExternalLinks($html, $config);
    $discoveredImages        = leAuditExtractImages($html, $url, $config);
    $discoveredLinkContexts  = leAuditExtractLinkContexts($html, $url, $config);
    $discoveredImageContexts = leAuditExtractImageContexts($html, $url, $config);

    return [
        'url'                       => $url,
        'status'                    => $fetch['status'],
        'final_url'                 => $fetch['final_url'],
        'load_time_ms'              => $fetch['load_time_ms'],
        'title'                     => $title,
        'title_length'              => $titleLen,
        'meta_description'          => $description,
        'meta_description_length'   => $descLen,
        'canonical'                 => $canonical,
        'h1_count'                  => $h1Count,
        'h1_text'                   => $h1Text,
        'noindex'                   => $noindex,
        'images_without_alt'        => $imgsNoAlt,
        'issues'                    => $issues,
        'warnings'                  => $warnings,
        'scanned_at'                => gmdate('c'),
        'discovered_links'          => $discoveredLinks,
        'discovered_external_links' => $discoveredExternalLinks,
        'discovered_images'         => $discoveredImages,
        'discovered_link_contexts'  => $discoveredLinkContexts,
        'discovered_image_contexts' => $discoveredImageContexts,
    ];
}

function leAuditLoadSitemapUrls(array $config): array
{
    $urls           = [];
    $sitemapsToRead = [];
    $sitemapsRead   = [];
    $diagnostics    = [];

    foreach ($config['sitemap_candidates'] as $sitemapUrl) {
        $sitemapUrl = trim((string) $sitemapUrl);
        if ($sitemapUrl !== '') {
            $sitemapsToRead[] = $sitemapUrl;
        }
    }

    while (!empty($sitemapsToRead)) {
        $currentSitemap = array_shift($sitemapsToRead);

        if ($currentSitemap === '' || isset($sitemapsRead[$currentSitemap])) {
            continue;
        }

        $sitemapsRead[$currentSitemap] = true;
        $fetch = leAuditFetchUrl($currentSitemap, $config, $config['sitemap_timeout']);

        if ($fetch['status'] < 200 || $fetch['status'] >= 300 || $fetch['body'] === '') {
            $errDetail     = $fetch['error'] !== '' ? ': ' . $fetch['error'] : '';
            $diagnostics[] = 'SKIP ' . $currentSitemap . ' (HTTP ' . $fetch['status'] . $errDetail . ')';
            continue;
        }

        $body = $fetch['body'];

        if (!preg_match_all('#<loc>(.*?)</loc>#is', $body, $matches)) {
            $diagnostics[] = 'SKIP ' . $currentSitemap . ' (no <loc> entries found)';
            continue;
        }

        $sampleLocs = array_slice($matches[1], 0, 3);
        foreach ($sampleLocs as &$s) {
            $s = trim(html_entity_decode(strip_tags($s), ENT_QUOTES, 'UTF-8'));
        }
        unset($s);
        $diagnostics[] = 'SAMPLE locs from ' . $currentSitemap . ': ' . implode(' | ', $sampleLocs);

        $isSitemapIndex = stripos($body, '<sitemapindex') !== false
                       || (stripos($body, '<sitemap>') !== false && stripos($body, '<url>') === false);
        $foundUnique    = [];
        $subSitemaps    = 0;

        foreach ($matches[1] as $loc) {
            $loc = trim(html_entity_decode(strip_tags($loc), ENT_QUOTES, 'UTF-8'));

            if ($loc === '') {
                continue;
            }

            if ($isSitemapIndex) {
                if (!isset($sitemapsRead[$loc])) {
                    $sitemapsToRead[] = $loc;
                    $subSitemaps++;
                }
                continue;
            }

            $locHost = strtolower((string) parse_url($loc, PHP_URL_HOST));
            $locPath = (string) parse_url($loc, PHP_URL_PATH);
            if (in_array($locHost, $config['allowed_hosts'], true) && preg_match('#\.xml$#i', $locPath)) {
                if (!isset($sitemapsRead[$loc])) {
                    $sitemapsToRead[] = $loc;
                    $subSitemaps++;
                }
                continue;
            }

            $normalized = leAuditNormalizeUrl($loc, $config);
            if ($normalized !== '') {
                $urls[]                   = $normalized;
                $foundUnique[$normalized] = true;
            }
        }

        if ($isSitemapIndex) {
            $diagnostics[] = 'INDEX ' . $currentSitemap . ' -> queued ' . $subSitemaps . ' sub-sitemaps';
        } elseif ($subSitemaps > 0) {
            $diagnostics[] = 'MIXED ' . $currentSitemap . ' -> ' . count($foundUnique) . ' page URLs + queued ' . $subSitemaps . ' sub-sitemaps';
        } else {
            $foundUrls     = count($matches[1]);
            $diagnostics[] = 'OK ' . $currentSitemap . ' -> ' . count($foundUnique) . ' unique page URLs (' . $foundUrls . ' raw)';
        }
    }

    return [
        'urls'        => array_values(array_unique($urls)),
        'diagnostics' => $diagnostics,
    ];
}

function leAuditAddUrlsToQueue(array &$queue, array $urls, array $config, string $sourceUrl = ''): int
{
    $added = 0;

    if (!isset($queue['referrers'])) {
        $queue['referrers'] = [];
    }

    foreach ($urls as $url) {
        $normalized = leAuditNormalizeUrl($url, $config);

        if ($normalized === '') {
            continue;
        }

        if (isset($queue['seen'][$normalized]) || isset($queue['scanned'][$normalized])) {
            continue;
        }

        if (count($queue['seen']) >= $config['max_queue_size']) {
            break;
        }

        $queue['pending'][]         = $normalized;
        $queue['seen'][$normalized] = true;

        if ($sourceUrl !== '' && !isset($queue['referrers'][$normalized])) {
            $queue['referrers'][$normalized] = $sourceUrl;
        }

        $added++;
    }

    $queue['pending']    = array_values(array_unique($queue['pending']));
    $queue['updated_at'] = gmdate('c');
    return $added;
}

function leAuditBuildSummary(array $results): array
{
    $summary = [
        'total_scanned'        => 0,
        'critical_pages'       => 0,
        'warning_pages'        => 0,
        'passed_pages'         => 0,
        'not_found_pages'      => 0,
        'server_error_pages'   => 0,
        'redirect_pages'       => 0,
        'missing_titles'       => 0,
        'missing_descriptions' => 0,
        'missing_canonicals'   => 0,
        'noindex_pages'        => 0,
        'slow_pages'           => 0,
        'images_missing_alt'   => 0,
        'avg_load_time_ms'     => 0,
        'broken_links_total'   => !empty($results['broken_links']) ? count($results['broken_links']) : 0,
    ];

    if (empty($results['items']) || !is_array($results['items'])) {
        return $summary;
    }

    $totalLoadTime = 0;

    foreach ($results['items'] as $item) {
        $summary['total_scanned']++;

        if (!empty($item['issues'])) {
            $summary['critical_pages']++;
        } elseif (!empty($item['warnings'])) {
            $summary['warning_pages']++;
        } else {
            $summary['passed_pages']++;
        }

        if ((int) $item['status'] === 404) {
            $summary['not_found_pages']++;
        }
        if ((int) $item['status'] >= 500) {
            $summary['server_error_pages']++;
        }
        if ((int) $item['status'] >= 300 && (int) $item['status'] < 400) {
            $summary['redirect_pages']++;
        }
        if (trim((string) $item['title']) === '') {
            $summary['missing_titles']++;
        }
        if (trim((string) $item['meta_description']) === '') {
            $summary['missing_descriptions']++;
        }
        if (trim((string) $item['canonical']) === '') {
            $summary['missing_canonicals']++;
        }
        if (!empty($item['noindex'])) {
            $summary['noindex_pages']++;
        }
        if ((int) $item['load_time_ms'] > 3000) {
            $summary['slow_pages']++;
        }
        if (!empty($item['images_without_alt']) && (int) $item['images_without_alt'] > 0) {
            $summary['images_missing_alt']++;
        }

        $totalLoadTime += (int) $item['load_time_ms'];
    }

    if ($summary['total_scanned'] > 0) {
        $summary['avg_load_time_ms'] = (int) round($totalLoadTime / $summary['total_scanned']);
    }

    $extraPassed = (int) ($results['passed_pages_count'] ?? 0);
    $summary['passed_pages']  += $extraPassed;
    $summary['total_scanned'] += $extraPassed;

    return $summary;
}

function leAuditMergeBrokenContext(array $brokenLink, array $context): array
{
    $brokenLink['link_text']      = $context['link_text']      ?? '';
    $brokenLink['raw_href']       = $context['raw_href']       ?? '';
    $brokenLink['source_excerpt'] = $context['source_excerpt'] ?? '';
    $brokenLink['html_snippet']   = $context['html_snippet']   ?? '';
    return $brokenLink;
}

function leAuditFormatEta(int $seconds): string
{
    if ($seconds <= 0) {
        return 'calculating';
    }

    $hours   = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs    = $seconds % 60;

    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }

    if ($minutes > 0) {
        return $minutes . 'm ' . $secs . 's';
    }

    return $secs . 's';
}

function leAuditExportCsv(array $results): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $filename = 'lottoexpert-full-site-audit-' . gmdate('Ymd-His') . '.csv';
    $headers  = [
        'Record Type', 'URL', 'Status', 'Load Time MS', 'Title', 'Title Length',
        'Meta Description', 'Meta Description Length', 'Canonical',
        'H1 Count', 'H1 Text', 'Noindex', 'Images Without Alt',
        'Issues', 'Warnings', 'Scanned At',
        'Broken Link Type', 'Source Page', 'Broken URL', 'Broken Status',
        'Link Text Or Image Alt', 'Raw Href Or Src', 'Page Text Near Link', 'HTML Snippet', 'Detected At',
    ];

    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    $fp = fopen('php://output', 'w');
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, $headers);

    if (!empty($results['items'])) {
        foreach ($results['items'] as $item) {
            fputcsv($fp, [
                'page',
                $item['url']                     ?? '',
                $item['status']                  ?? '',
                $item['load_time_ms']            ?? '',
                $item['title']                   ?? '',
                $item['title_length']            ?? '',
                $item['meta_description']        ?? '',
                $item['meta_description_length'] ?? '',
                $item['canonical']               ?? '',
                $item['h1_count']                ?? '',
                $item['h1_text']                 ?? '',
                !empty($item['noindex']) ? 'Yes' : 'No',
                $item['images_without_alt']      ?? 0,
                implode(' | ', $item['issues']   ?? []),
                implode(' | ', $item['warnings'] ?? []),
                $item['scanned_at']              ?? '',
                '', '', '', '', '', '', '', '', '',
            ]);
        }
    }

    if (!empty($results['broken_links'])) {
        foreach ($results['broken_links'] as $broken) {
            fputcsv($fp, [
                'broken_link',
                '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                $broken['link_type']      ?? '',
                $broken['source_page']    ?? '',
                $broken['broken_url']     ?? '',
                $broken['status']         ?? '',
                $broken['link_text']      ?? '',
                $broken['raw_href']       ?? '',
                $broken['source_excerpt'] ?? '',
                $broken['html_snippet']   ?? '',
                $broken['found_at']       ?? '',
            ]);
        }
    }

    fclose($fp);
    exit;
}

// ============================================================================
// Controller
// ============================================================================

class LeauditController extends BaseController
{
    /**
     * Component configuration — site root, allowed hosts, crawl limits.
     * Edit this array to point the scanner at a different domain.
     */
    private function getConfig(): array
    {
        return [
            'site_root'          => 'https://lottoexpert.net',
            'batch_limit'        => 15,
            'max_queue_size'     => 25000,
            'request_timeout'    => 4,
            'allowed_host'       => 'lottoexpert.net',
            'allowed_hosts'      => ['lottoexpert.net', 'www.lottoexpert.net'],
            'ignore_patterns'    => [
                '/logout',
                '/login?return=',
                '/component/users',
                '/administrator',
                '/cart',
                '/checkout',
                '/?print=',
                '&print=',
                'format=feed',
                'tmpl=component',
                '#',
                'mailto:',
                'tel:',
                'javascript:',
            ],
            'sitemap_timeout'    => 30,
            'sitemap_candidates' => [
                'https://lottoexpert.net/sitemap_xml.xml',
            ],
        ];
    }

    /**
     * display() is the default Joomla task.
     *
     * It handles every audit_action POST/GET value before delegating to the
     * view for HTML rendering.  AJAX and CSV responses exit early.
     */
    public function display($cachable = false, $urlparams = [])
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        // ── Access guard ─────────────────────────────────────────────────────
        $user = $app->getIdentity();
        if (!$user || !$user->id || !$user->authorise('core.login.site')) {
            throw new \RuntimeException('Access Denied', 403);
        }

        $config = $this->getConfig();

        // ── State ─────────────────────────────────────────────────────────────
        [$queue, $results] = leAuditInitState($config);

        $scanOptions = leAuditSessionGet('le_scan_options', []);
        if (empty($scanOptions)) {
            $scanOptions = leAuditGetDefaultOptions();
        }
        foreach (leAuditGetDefaultOptions() as $k => $v) {
            if (!array_key_exists($k, $scanOptions)) {
                $scanOptions[$k] = $v;
            }
        }

        $message = '';
        $action  = $input->getCmd('audit_action', '');

        // ── Token check ───────────────────────────────────────────────────────
        if ($action !== '' && !Session::checkToken('post')) {
            $message = 'Invalid session token. Refresh the page and try again.';
            $action  = '';
        }

        // ── Action dispatch ───────────────────────────────────────────────────
        if ($action === 'save_options' || $action === 'save_options_ajax') {
            $scanOptions = [
                'check_broken_internal'       => (bool) $input->getInt('opt_broken_internal',       0),
                'check_broken_internal_links' => (bool) $input->getInt('opt_broken_internal_links', 0),
                'check_broken_external'       => (bool) $input->getInt('opt_broken_external',       0),
                'check_broken_images'         => (bool) $input->getInt('opt_broken_images',         0),
                'check_seo'                   => (bool) $input->getInt('opt_seo',                   0),
                'check_speed'                 => (bool) $input->getInt('opt_speed',                 0),
                'check_alt_text'              => (bool) $input->getInt('opt_alt_text',              0),
                'store_passed_pages'          => (bool) $input->getInt('opt_store_passed',          0),
            ];
            leAuditSessionSet('le_scan_options', $scanOptions);
            $message = 'Scan options saved.';

            if ($action === 'save_options_ajax') {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                    header('Cache-Control: no-store, no-cache, must-revalidate');
                }
                echo json_encode(['ok' => true, 'message' => 'Saved', 'options' => $scanOptions]);
                exit;
            }
        } elseif ($action === 'reset') {
            $session = $app->getSession();
            $session->set('le_queue', null);
            $session->set('le_results', null);
            [$queue, $results] = leAuditInitState($config);
            $message = 'Audit reset successfully.';
        } elseif ($action === 'stop_all') {
            $queue['pending'] = [];
            if (isset($queue['ext_pending'])) {
                $queue['ext_pending'] = [];
            }
            if (isset($queue['int_link_pending'])) {
                $queue['int_link_pending'] = [];
            }
            leAuditSessionSet('le_queue', $queue);
            $message = 'Scan stopped. Results collected so far are preserved. Click Reset Audit to start a fresh scan.';
        } elseif ($action === 'discover_sitemap') {
            $sitemapResult = leAuditLoadSitemapUrls($config);
            $sitemapUrls   = $sitemapResult['urls'];
            $diagnostics   = $sitemapResult['diagnostics'];
            $added         = leAuditAddUrlsToQueue($queue, $sitemapUrls, $config);
            leAuditSessionSet('le_queue', $queue);
            $alreadyQueued = count($sitemapUrls) - $added;
            $diagText      = !empty($diagnostics) ? ' Details: ' . implode(' | ', $diagnostics) : '';
            $message = 'Sitemap discovery complete. Found ' . count($sitemapUrls) . ' page URLs. '
                     . 'Added ' . (int) $added . ' new URLs to queue'
                     . ($alreadyQueued > 0 ? ' (' . $alreadyQueued . ' already queued).' : '.') . $diagText;
        } elseif ($action === 'scan_batch' || $action === 'scan_batch_ajax') {
            @set_time_limit(180);
            @ignore_user_abort(true);

            // Re-read options from batch hidden fields so settings survive session regeneration
            $optionKeys = array_keys(leAuditGetDefaultOptions());
            if ($input->getInt('batch_opt_check_seo', -1) !== -1) {
                foreach ($optionKeys as $k) {
                    $scanOptions[$k] = (bool) $input->getInt('batch_opt_' . $k, 0);
                }
                leAuditSessionSet('le_scan_options', $scanOptions);
            }

            $processed    = 0;
            $newUrlsFound = 0;
            $batchLimit   = (int) $config['batch_limit'];

            if (!isset($queue['ext_pending']))        { $queue['ext_pending']        = []; }
            if (!isset($queue['ext_seen']))           { $queue['ext_seen']           = []; }
            if (!isset($queue['ext_referrers']))      { $queue['ext_referrers']      = []; }
            if (!isset($queue['link_contexts']))      { $queue['link_contexts']      = []; }
            if (!isset($queue['ext_contexts']))       { $queue['ext_contexts']       = []; }
            if (!isset($queue['int_link_pending']))   { $queue['int_link_pending']   = []; }
            if (!isset($queue['int_link_seen']))      { $queue['int_link_seen']      = []; }
            if (!isset($queue['int_link_referrers'])) { $queue['int_link_referrers'] = []; }
            if (!isset($queue['int_link_contexts']))  { $queue['int_link_contexts']  = []; }

            $batchStartedAt = microtime(true);

            while ($processed < $batchLimit && !empty($queue['pending'])) {
                $url        = array_shift($queue['pending']);
                $normalized = leAuditNormalizeUrl($url, $config);

                if ($normalized === '' || isset($queue['scanned'][$normalized])) {
                    continue;
                }

                $result                  = leAuditCheckUrl($normalized, $config, $scanOptions);
                $discoveredLinks         = $result['discovered_links'];
                $discoveredExtLinks      = $result['discovered_external_links'] ?? [];
                $discoveredImages        = $result['discovered_images']         ?? [];
                $discoveredLinkContexts  = $result['discovered_link_contexts']  ?? [];
                $discoveredImageContexts = $result['discovered_image_contexts'] ?? [];
                unset(
                    $result['discovered_links'],
                    $result['discovered_external_links'],
                    $result['discovered_images'],
                    $result['discovered_link_contexts'],
                    $result['discovered_image_contexts']
                );

                $hasIssues   = !empty($result['issues']);
                $hasWarnings = !empty($result['warnings']);
                $isPassing   = !$hasIssues && !$hasWarnings;

                if ($isPassing && !($scanOptions['store_passed_pages'] ?? false)) {
                    $results['passed_pages_count'] = ($results['passed_pages_count'] ?? 0) + 1;
                } else {
                    $result['source_page']         = $queue['referrers'][$normalized] ?? '';
                    $results['items'][$normalized] = $result;
                }
                $queue['scanned'][$normalized] = true;

                $newUrlsFound += leAuditAddUrlsToQueue($queue, $discoveredLinks, $config, $normalized);
                foreach ($discoveredLinks as $contextUrl) {
                    if (isset($discoveredLinkContexts[$contextUrl]) && !isset($queue['link_contexts'][$contextUrl])) {
                        $queue['link_contexts'][$contextUrl] = $discoveredLinkContexts[$contextUrl];
                    }
                }

                $extAssets = [];
                if ($scanOptions['check_broken_external'] ?? true) {
                    $extAssets = array_merge($extAssets, $discoveredExtLinks);
                }
                if ($scanOptions['check_broken_images'] ?? true) {
                    $extAssets = array_merge($extAssets, $discoveredImages);
                }
                foreach ($extAssets as $extUrl) {
                    if (!isset($queue['ext_seen'][$extUrl]) && count($queue['ext_seen']) < 10000) {
                        $queue['ext_pending'][]          = $extUrl;
                        $queue['ext_seen'][$extUrl]      = true;
                        $queue['ext_referrers'][$extUrl] = $normalized;
                        if (isset($discoveredImageContexts[$extUrl])) {
                            $queue['ext_contexts'][$extUrl] = $discoveredImageContexts[$extUrl];
                        } elseif (isset($discoveredLinkContexts[$extUrl])) {
                            $queue['ext_contexts'][$extUrl] = $discoveredLinkContexts[$extUrl];
                        }
                    }
                }

                if ($scanOptions['check_broken_internal_links'] ?? true) {
                    foreach ($discoveredLinks as $intLinkUrl) {
                        if (!isset($queue['int_link_seen'][$intLinkUrl]) && count($queue['int_link_seen']) < 20000) {
                            $queue['int_link_pending'][]              = $intLinkUrl;
                            $queue['int_link_seen'][$intLinkUrl]      = true;
                            $queue['int_link_referrers'][$intLinkUrl] = $normalized;
                            if (isset($discoveredLinkContexts[$intLinkUrl])) {
                                $queue['int_link_contexts'][$intLinkUrl] = $discoveredLinkContexts[$intLinkUrl];
                            }
                        }
                    }
                }

                if ($scanOptions['check_broken_internal'] ?? true) {
                    $isBroken = ($result['status'] === 0 || $result['status'] === 404
                                 || ($result['status'] >= 400 && $result['status'] < 600));
                    if ($isBroken) {
                        $referrer = $queue['referrers'][$normalized] ?? '';
                        if ($referrer !== '') {
                            if (!isset($results['broken_links'])) {
                                $results['broken_links'] = [];
                            }
                            $results['broken_links'][] = leAuditMergeBrokenContext([
                                'source_page' => $referrer,
                                'broken_url'  => $normalized,
                                'status'      => $result['status'],
                                'found_at'    => gmdate('c'),
                                'link_type'   => 'internal',
                            ], $queue['link_contexts'][$normalized] ?? []);
                        }
                    }
                }

                $processed++;
            }

            // ── External asset HEAD-checks (up to 10 per batch) ──────────────
            $extChecked = 0;
            while ($extChecked < 10 && !empty($queue['ext_pending'])) {
                $extUrl    = array_shift($queue['ext_pending']);
                $extStatus = leAuditHeadCheckUrl($extUrl);
                $isBroken  = ($extStatus === 0 || $extStatus === 404
                               || ($extStatus >= 400 && $extStatus < 600));
                if ($isBroken) {
                    $extReferrer = $queue['ext_referrers'][$extUrl] ?? '';
                    if ($extReferrer !== '') {
                        if (!isset($results['broken_links'])) {
                            $results['broken_links'] = [];
                        }
                        $isImage = (bool) preg_match('#\.(jpg|jpeg|png|gif|webp|svg|ico|bmp)(\?.*)?$#i', $extUrl);
                        $results['broken_links'][] = leAuditMergeBrokenContext([
                            'source_page' => $extReferrer,
                            'broken_url'  => $extUrl,
                            'status'      => $extStatus,
                            'found_at'    => gmdate('c'),
                            'link_type'   => $isImage ? 'image' : 'external',
                        ], $queue['ext_contexts'][$extUrl] ?? []);
                    }
                }
                $extChecked++;
            }

            // ── Internal link HEAD-checks (up to 10 per batch) ───────────────
            $intLinkChecked = 0;
            while ($intLinkChecked < 10 && !empty($queue['int_link_pending'])) {
                $intLinkUrl = array_shift($queue['int_link_pending']);

                if (isset($queue['scanned'][$intLinkUrl])) {
                    $intLinkChecked++;
                    continue;
                }

                $intLinkStatus = leAuditHeadCheckUrl($intLinkUrl);
                $isBroken      = ($intLinkStatus === 0 || $intLinkStatus === 404
                                   || ($intLinkStatus >= 400 && $intLinkStatus < 600));
                if ($isBroken) {
                    $intLinkReferrer = $queue['int_link_referrers'][$intLinkUrl] ?? '';
                    if ($intLinkReferrer !== '') {
                        if (!isset($results['broken_links'])) {
                            $results['broken_links'] = [];
                        }
                        $results['broken_links'][] = leAuditMergeBrokenContext([
                            'source_page' => $intLinkReferrer,
                            'broken_url'  => $intLinkUrl,
                            'status'      => $intLinkStatus,
                            'found_at'    => gmdate('c'),
                            'link_type'   => 'internal',
                        ], $queue['int_link_contexts'][$intLinkUrl] ?? []);
                    }
                }
                $intLinkChecked++;
            }

            $results['summary']    = leAuditBuildSummary($results);
            $results['updated_at'] = gmdate('c');
            $queue['updated_at']   = gmdate('c');
            leAuditSessionSet('le_queue', $queue);
            leAuditSessionSet('le_results', $results);

            $pendingAfter        = count($queue['pending']);
            $extPendingAfter     = count($queue['ext_pending']      ?? []);
            $intLinkPendingAfter = count($queue['int_link_pending'] ?? []);
            $message = 'Batch complete. Scanned ' . (int) $processed . ' pages. '
                     . (int) $newUrlsFound . ' new pages discovered. '
                     . (int) $pendingAfter . ' pages still pending. '
                     . (int) $extChecked . ' external asset(s) checked, '
                     . (int) $extPendingAfter . ' still queued. '
                     . (int) $intLinkChecked . ' internal link(s) HEAD-checked, '
                     . (int) $intLinkPendingAfter . ' still queued.';

            if ($action === 'scan_batch_ajax') {
                $ajaxPendingCount = count($queue['pending'] ?? []);
                $ajaxSeenCount    = count($queue['seen']    ?? []);
                $ajaxScannedCount = count($queue['scanned'] ?? []);
                $ajaxProgressPct  = ($ajaxSeenCount > 0)
                    ? min(100, (int) round(($ajaxScannedCount / $ajaxSeenCount) * 100))
                    : 0;
                $batchDurationMs = (int) round((microtime(true) - $batchStartedAt) * 1000);
                $secondsPerPage  = $processed > 0 ? max(1, (int) ceil(($batchDurationMs / 1000) / $processed)) : 0;
                $etaSeconds      = ($secondsPerPage > 0 && $ajaxPendingCount > 0) ? $secondsPerPage * $ajaxPendingCount : 0;
                $etaLabel        = $ajaxPendingCount > 0 ? leAuditFormatEta($etaSeconds) : 'complete';

                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                    header('Cache-Control: no-store, no-cache, must-revalidate');
                }
                echo json_encode([
                    'ok'                => true,
                    'pending'           => $ajaxPendingCount,
                    'scanned'           => $ajaxScannedCount,
                    'seen'              => $ajaxSeenCount,
                    'progress_pct'      => $ajaxProgressPct,
                    'message'           => $message,
                    'batch_duration_ms' => $batchDurationMs,
                    'seconds_per_page'  => $secondsPerPage,
                    'eta_seconds'       => $etaSeconds,
                    'eta_label'         => $etaLabel,
                    'done'              => ($ajaxPendingCount === 0),
                ]);
                exit;
            }
        } elseif ($action === 'export_csv') {
            $results['summary'] = leAuditBuildSummary($results);
            leAuditExportCsv($results); // streams CSV and exits
        }

        // ── Prepare view data ─────────────────────────────────────────────────
        $results = leAuditSessionGet('le_results', []);
        $queue   = leAuditSessionGet('le_queue',   []);
        $summary = leAuditBuildSummary($results);

        $pendingCount = !empty($queue['pending']) ? count($queue['pending']) : 0;
        $seenCount    = !empty($queue['seen'])    ? count($queue['seen'])    : 0;
        $scannedCount = !empty($queue['scanned']) ? count($queue['scanned']) : 0;
        $progressPct  = ($seenCount > 0) ? min(100, (int) round(($scannedCount / $seenCount) * 100)) : 0;

        $criticalItems   = [];
        $warningItems    = [];
        $passedItems     = [];
        $recentItems     = [];
        $brokenLinkItems = [];

        if (!empty($results['items'])) {
            $allItems = array_values($results['items']);

            foreach ($allItems as $item) {
                if (!empty($item['issues'])) {
                    $criticalItems[] = $item;
                } elseif (!empty($item['warnings'])) {
                    $warningItems[] = $item;
                } else {
                    $passedItems[] = $item;
                }
            }

            usort($allItems, function ($a, $b) {
                return strcmp($b['scanned_at'] ?? '', $a['scanned_at'] ?? '');
            });
            $recentItems = array_slice($allItems, 0, 25);
        }

        $criticalItems   = array_slice($criticalItems,  0, 100);
        $warningItems    = array_slice($warningItems,   0, 100);
        $passedItems     = array_slice($passedItems,    0, 100);

        if (!empty($results['broken_links'])) {
            $brokenLinkItems = array_slice($results['broken_links'], 0, 500);
        }

        // ── Pass data into the view ───────────────────────────────────────────
        $view = $this->getView('audit', 'html');

        $view->baseUrl         = Route::_('index.php?option=com_leaudit');
        $view->token           = Session::getFormToken();
        $view->pendingCount    = $pendingCount;
        $view->seenCount       = $seenCount;
        $view->scannedCount    = $scannedCount;
        $view->progressPct     = $progressPct;
        $view->summary         = $summary;
        $view->criticalItems   = $criticalItems;
        $view->warningItems    = $warningItems;
        $view->passedItems     = $passedItems;
        $view->recentItems     = $recentItems;
        $view->brokenLinkItems = $brokenLinkItems;
        $view->scanOptions     = $scanOptions;
        $view->message         = $message;

        parent::display($cachable, $urlparams);

        return $this;
    }
}
