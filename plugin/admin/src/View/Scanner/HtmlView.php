<?php

namespace LottoExpert\Component\LeSiteAudit\Administrator\View\Scanner;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    /** @var string  Component base URL for forms/AJAX */
    public string $baseUrl = '';

    /** @var string  Joomla CSRF form token name */
    public string $token = '';

    /** @var int  Pages remaining in queue */
    public int $pendingCount = 0;

    /** @var int  Unique pages ever queued */
    public int $seenCount = 0;

    /** @var int  Pages fully scanned */
    public int $scannedCount = 0;

    /** @var int  Progress percentage 0-100 */
    public int $progressPct = 0;

    /** @var array  Summary statistics */
    public array $summary = [];

    /** @var array  Pages with critical issues */
    public array $criticalItems = [];

    /** @var array  Pages with warnings only */
    public array $warningItems = [];

    /** @var array  Pages with no issues */
    public array $passedItems = [];

    /** @var array  Most recently scanned pages */
    public array $recentItems = [];

    /** @var array  Broken link records */
    public array $brokenLinkItems = [];

    /** @var array  Current scan options */
    public array $scanOptions = [];

    /** @var string  Status/info message to display */
    public string $message = '';

    public function display($tpl = null): void
    {
        ToolbarHelper::title('LottoExpert — Site Audit Scanner', 'search');
        parent::display($tpl);
    }
}
