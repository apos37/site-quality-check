=== Site Quality Check ===
Contributors: apos37
Tags: quality, checklist, maintenance, content audit, site health
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keep every site up to date with editable checklists, stale content tracking, and on-demand quality checks.

== Description ==

**Site Quality Check** helps you stay on top of ongoing site maintenance with editable checklists, stale content tracking, and one-click quality checks — all from a single dashboard just under your WordPress Dashboard menu.

**Features:**

- Editable, drag-and-drop checklists organized into sections (daily, weekly, monthly, quarterly, annual) with recurrence-based auto-reset
- Multiple checklist tabs (Developer, Designer, Content Editor by default) with per-tab role-based access control
- Mark items complete, snooze until the next cycle, or permanently omit them
- Stale content viewer with configurable warning, danger, and critical thresholds
- Quick action buttons: contact form notification test (Gravity Forms), 404 check on key pages, robots.txt/sitemap reachability, SSL certificate expiration
- Dashboard widgets including a Site Health summary
- Integrations page linking to complementary PluginRx plugins
- Import/export settings and checklists as JSON, reset to defaults, and clear stored data on demand

**Integrations (detected automatically, all optional):**

- Broken Link Notifier — adds a broken links count widget
- Yoast SEO — surfaces content missing meta descriptions or titles
- Gravity Forms — enables the contact form test quick action and contact form selector
- PluginRx Control Center / Agent — view quality data across all your managed sites

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/site-quality-check/` directory, or install directly through the Plugins screen.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to Quality Check in the admin menu to review your default checklists.
4. Visit Quality Check > Settings to configure stale content thresholds, included post types, and your contact page/form.

== Frequently Asked Questions ==

= Does this plugin work standalone? =
Yes. All integrations are optional and only activate if the related plugin is installed.

= Can I control who sees which checklist? =
Yes. Each checklist tab can be restricted to specific roles. Administrators always have access regardless of the assigned roles.

= What happens to checklist items I mark complete? =
Depending on the section's recurrence (daily, weekly, monthly, quarterly, annually), completed items automatically reset back to incomplete after that interval passes.

= What's the difference between snoozing and omitting an item? =
Snoozing hides an item until its next recurrence cycle, then it reappears. Omitting hides it permanently.

= How do I change how many days before content is considered stale? =
Go to Quality Check > Settings and find the Stale Content Thresholds box. You can set the number of days for Warning, Danger, and Critical independently — content is flagged once it passes the Warning threshold, and moves into Danger and then Critical as it ages further without being updated.

= Will uninstalling this plugin delete my data? =
Only if you've checked "Delete all plugin data when this plugin is deleted" under Quality Check > Advanced. This is unchecked by default.

= Where can I find developer documentation? =
Visit the Developer Docs link on the plugin's row on the Plugins screen, or see https://pluginrx.com/docs/plugin/site-quality-check/.

== Screenshots ==

1. Dashboard with Site Health, Checklists, Stale Content, and Broken Links widgets
2. Editable, drag-and-drop checklist with sections and recurrence
3. Stale content viewer with tiered warnings
4. Integrations page

== Changelog ==

= 1.0.0 =
* Initial release of Site Quality Check