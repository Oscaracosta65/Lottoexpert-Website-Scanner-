<?php
/**
 * com_leaudit — audit view class.
 *
 * The controller has already set all public properties on this object
 * before calling display().  The template reads them via $this->.
 */
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;

class LeauditViewAudit extends HtmlView
{
    /** @var string  Component base URL for forms/AJAX */
    public $baseUrl = '';

    /** @var string  Joomla CSRF form token name */
    public $token = '';

    /** @var int  Pages remaining in the crawl queue */
    public $pendingCount = 0;

    /** @var int  Unique pages ever queued */
    public $seenCount = 0;

    /** @var int  Pages fully scanned */
    public $scannedCount = 0;

    /** @var int  Progress percentage 0–100 */
    public $progressPct = 0;

    /** @var array  Aggregated statistics */
    public $summary = [];

    /** @var array  Pages with at least one critical issue */
    public $criticalItems = [];

    /** @var array  Pages with warnings only */
    public $warningItems = [];

    /** @var array  Pages with no issues or warnings */
    public $passedItems = [];

    /** @var array  Most recently scanned pages (up to 25) */
    public $recentItems = [];

    /** @var array  Broken link records */
    public $brokenLinkItems = [];

    /** @var array  Active scan option flags */
    public $scanOptions = [];

    /** @var string  Status/info message from the last action */
    public $message = '';

    public function display($tpl = null)
    {
        parent::display($tpl);
    }
}
