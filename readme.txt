=== TranslateAI for TranslatePress ===
Contributors: vage91
Donate link: https://buymeacoffee.com/vage91
Tags: translatepress, translation, ai, ollama, automatic translation
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered automatic translation for TranslatePress using local Ollama models, with optional dual-agent quality control.

== Description ==

TranslateAI for TranslatePress automates the translation of your TranslatePress dictionary tables using local AI models via [Ollama](https://ollama.com/). No API costs, no data sent to external services — everything runs on your own hardware.

**Key Features:**

* **Translator + Judge mode** — A translator model generates translations and a separate judge model validates quality before saving
* **Translator Only mode** — Faster single-model mode with structural artifact protection, no quality validation step
* **Batch processing** — Process multiple strings per request with configurable delay to prevent context bleeding
* **Site context injection** — Describe your site's domain so models correctly handle specialized terminology
* **Separate judge endpoint** — Run the judge model on a different machine to distribute the workload
* **Smart auto-pass** — URLs and file extensions are passed through untranslated automatically
* **CJK language support** — Length validation is disabled for morphologically compact languages (Chinese, Japanese, Korean, Thai, etc.)
* **Deep sanitization** — Multi-layer pipeline strips JSON artifacts, markdown, unicode escape sequences and HTML from model outputs before saving
* **Failed strings export** — Download a CSV of all strings that failed translation during a session
* **Real-time progress** — Live counters, activity log and agent review cards update as translations are processed

**Requirements:**

* [TranslatePress](https://translatepress.com/) plugin (free or business) must be installed and active
* A running [Ollama](https://ollama.com/) instance accessible from your WordPress server
* At least one language model pulled in Ollama (e.g. `mistral-small:22b`)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via the WordPress plugin screen
2. Activate the plugin — TranslatePress must be active or activation will be blocked
3. Go to **Settings → TranslateAI** to configure your Ollama endpoint and models
4. Select the TranslatePress dictionary table you want to translate
5. Press **Start** to begin

== Frequently Asked Questions ==

= Does this plugin send data to external services? =

No. All requests are sent directly to your own Ollama instance. No data is transmitted to any third-party service.

= Which Ollama models are recommended? =

For the translator: `mistral-small:22b` or `rinex20/translategemma3:12b` (specialized for translation).
For the judge: any general-purpose model with good multilingual understanding, e.g. `mistral-small:22b` or `qwen2.5:14b`. Abliterated variants are recommended if you translate content in sensitive domains.

= What happens to strings that fail translation? =

After a configurable number of retries, failed strings are marked as "skipped" (status 9) in the database and can be restored at any time using the Deep Clean function. You can also export them as a CSV for manual review.

= Can I run the translator and judge on different machines? =

Yes. Enable "Separate Judge Endpoint" in the configuration and specify the second machine's Ollama API URL.

= Is TranslatePress required? =

Yes. This plugin reads from and writes to TranslatePress dictionary tables (`trp_dictionary_*`). It will not activate without TranslatePress present.

== Screenshots ==

1. Main configuration panel with language, model and batch settings
2. Real-time activity log and agent review cards during translation
3. Database status box with live counters

== Changelog ==

= 1.0.0 =
* Initial release
* Translator + Judge dual-agent mode
* Translator Only mode
* Batch processing with configurable delay
* Site context injection for domain-specific terminology
* Separate judge endpoint support
* CJK language compact mode
* Deep sanitization pipeline
* Failed strings CSV export
* Real-time database status counters
* Auto-detection of source/target language from table name

== Upgrade Notice ==

= 1.0.0 =
Initial release.