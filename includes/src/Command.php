<?php
/**
 * Docket Cache.
 *
 * @author  Nawawi Jamili
 * @license MIT
 *
 * @see    https://github.com/nawawi/docket-cache
 */

namespace Nawawi\DocketCache;

\defined('ABSPATH') || exit;

use WP_CLI;
use WP_CLI_Command;

/**
 * Enables, disabled, updates, and checks the status of the Docket object cache.
 */
class Command extends WP_CLI_Command
{
    private $pt;

    public function __construct(Plugin $pt)
    {
        $this->pt = $pt;
    }

    private function halt_error($error)
    {
        WP_CLI::error($error, false);
        WP_CLI::halt(1);
    }

    private function halt_success($success)
    {
        WP_CLI::success($success, false);
        WP_CLI::halt(0);
    }

    private function halt_status($text, $status = 0)
    {
        WP_CLI::line($text);
        WP_CLI::halt($status);
    }

    private function title($text, $pad = 15)
    {
        return str_pad($text, $pad).': ';
    }

    private function status_color($status, $text)
    {
        switch ($status) {
            case 1:
                $text = WP_CLI::colorize("%g{$text}%n");
                break;
            default:
                $text = WP_CLI::colorize("%r{$text}%n");
                break;
        }

        return $text;
    }

    /**
     * Display the Docket Cache status.
     *
     * ## EXAMPLES
     *
     *  wp cache status
     */
    public function status()
    {
        $info = (object) $this->pt->get_info();
        $halt = $info->status_code ? 0 : 1;

        WP_CLI::line($this->title('Cache Status').$this->status_color($info->status_code, $info->status_text));
        WP_CLI::line($this->title('Cache Path').$info->cache_path);
        if ($this->pt->cf()->is_dctrue('STATS')) {
            WP_CLI::line($this->title('Cache Size').$info->cache_size);
        }
        WP_CLI::halt($halt);
    }

    /**
     * Enables the Docket Cache Drop-In file.
     *
     * Default behavior is to create the object cache Drop-In,
     * unless an unknown object cache Drop-In is present.
     *
     * ## EXAMPLES
     *
     *  wp cache enable
     */
    public function enable()
    {
        if ($this->pt->cx()->exists()) {
            if ($this->pt->cx()->validate()) {
                WP_CLI::line(__('Docket object cache already enabled.', 'docket-cache'));
                WP_CLI::halt(0);
            }

            $this->halt_error(__('An unknown object cache Drop-In was found. To use Docket object cache, run: wp cache update.', 'docket-cache'));
        }

        if ($this->pt->cx()->install()) {
            $this->halt_success(__('Object cache enabled.', 'docket-cache'));
        }

        $this->halt_error(__('Object cache could not be enabled.', 'docket-cache'));
    }

    /**
     * Disables the Docket Cache Drop-In file.
     *
     * Default behavior is to delete the object cache Drop-In,
     * unless an unknown object cache Drop-In is present.
     *
     * ## EXAMPLES
     *
     *  wp cache disable
     */
    public function disable()
    {
        if (!$this->pt->cx()->exists()) {
            $this->halt_error(__('No object cache Drop-In found.', 'docket-cache'));
        }

        if (!$this->pt->cx()->validate()) {
            $this->halt_error(__('An unknown object cache Drop-In was found. To use Docket run: wp cache update.', 'docket-cache'));
        }

        if ($this->pt->cx()->uninstall()) {
            $this->halt_success(__('Object cache disabled.', 'docket-cache'));
        }

        $this->halt_error(__('Object cache could not be disabled.', 'docket-cache'));
    }

    /**
     * Updates the Docket Cache Drop-In file.
     *
     * Default behavior is to overwrite any existing object cache Drop-In.
     *
     * ## EXAMPLES
     *
     *  wp cache update
     *
     * @subcommand update
     */
    public function update_dropino()
    {
        if ($this->pt->cx()->install()) {
            $this->halt_success(__('Updated object cache Drop-In and enabled Docket object cache.', 'docket-cache'));
        }
        $this->halt_error(__('Object cache Drop-In could not be updated.', 'docket-cache'));
    }

    /**
     * Flushes the object cache.
     *
     * Remove the object cache files.
     *
     * ## EXAMPLES
     *
     *  wp cache flush
     *
     * @subcommand flush
     */
    public function flush_cache()
    {
        if (false === $this->pt->flush_cache(true)) {
            $this->halt_error(__('Object cache could not be flushed.', 'docket-cache'));
        }

        $this->pt->cx()->undelay();
        $this->halt_success(__('The cache was flushed.', 'docket-cache'));
    }

    /**
     * Removes the Docket Cache lock files.
     *
     * Remove lock file.
     *
     * ## EXAMPLES
     *
     *  wp cache clearlock
     *
     * @subcommand flush
     */
    public function clearlock()
    {
        $this->pt->co()->clear_lock();
        $this->halt_success(__('The lock file flushed.', 'docket-cache'));
    }

    /**
     * Run the Docket Cache garbage collector (GC).
     *
     * Remove empty and older files, and execute various actions.
     *
     * ## EXAMPLES
     *
     *  wp cache gc
     *
     * @subcommand gc
     */
    public function rungc()
    {
        if (!has_filter('docketcache/garbage-collector')) {
            $this->halt_error(__('Garbage collector not available.', 'docket-cache'));
        }

        WP_CLI::line(__('Executing the garbage collector. Please wait..', 'docket-cache'));

        $pad = 35;
        $collect = apply_filters('docketcache/garbage-collector', true);

        WP_CLI::line($this->title(__('Cache MaxTTL', 'docket-cache'), $pad).$collect->cache_maxttl);
        WP_CLI::line($this->title(__('Cache File Limit', 'docket-cache'), $pad).$collect->cache_maxfile);
        WP_CLI::line($this->title(__('Cache Disk Limit', 'docket-cache'), $pad).$this->pt->normalize_size($collect->cache_maxdisk));
        WP_CLI::line($this->title(__('Cleanup Cache MaxTTL', 'docket-cache'), $pad).$collect->cleanup_maxttl);
        WP_CLI::line($this->title(__('Cleanup Cache File Limit', 'docket-cache'), $pad).$collect->cleanup_maxfile);
        WP_CLI::line($this->title(__('Cleanup Cache Precache Limit', 'docket-cache'), $pad).$collect->cleanup_precache_maxfile);
        WP_CLI::line($this->title(__('Cleanup Cache Disk Limit', 'docket-cache'), $pad).$collect->cleanup_maxdisk);
        WP_CLI::line($this->title(__('Total Cache Cleanup', 'docket-cache'), $pad).$collect->cache_cleanup);
        WP_CLI::line($this->title(__('Total Cache Ignored', 'docket-cache'), $pad).$collect->cache_ignore);
        WP_CLI::line($this->title(__('Total Cache File', 'docket-cache'), $pad).$collect->cache_file);

        $this->halt_success(__('Executing the garbage collector completed.', 'docket-cache'));
    }

    /**
     * Attempts to determine which object cache is being used.
     *
     * Note that the guesses made by this function are based on the
     * WP_Object_Cache classes that define the 3rd party object cache extension.
     * Changes to those classes could render problems with this function's
     * ability to determine which object cache is being used.
     *
     * ## EXAMPLES
     *
     *  wp cache type
     */
    public function type()
    {
        $this->halt_status($this->pt->slug.' (v'.$this->pt->version().')');
    }
}
