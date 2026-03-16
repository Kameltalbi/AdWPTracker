# AdWPtracker - Translation Guide

## Language Files

This plugin is **translation-ready** and comes with:

- **English** (default) - Built into the code
- **French** - Translation files included

### Files Structure

```
/languages/
├── adwptracker.pot          # Translation template
├── adwptracker-fr_FR.po     # French translation (editable)
└── adwptracker-fr_FR.mo     # French translation (compiled)
```

## How Translations Work

1. **English by default**: All text in the plugin is in English
2. **Auto-switch**: WordPress automatically loads French if:
   - WordPress language is set to French (Settings → General → Site Language)
   - Translation files are present in `/languages/` folder

## For Users: Installing French Translation

### Method 1: Automatic (Recommended)
1. Go to **Settings → General**
2. Set **Site Language** to **Français**
3. Save changes
4. Plugin will automatically display in French

### Method 2: Manual
1. Download translation files from plugin package
2. Upload to `/wp-content/plugins/adwptracker/languages/`
3. Make sure files are named correctly:
   - `adwptracker-fr_FR.po`
   - `adwptracker-fr_FR.mo`

## For Translators: Creating New Translations

### Requirements
- Poedit software (free): https://poedit.net/
- OR Loco Translate WordPress plugin

### Steps with Poedit:

1. **Open template**:
   - Launch Poedit
   - Open `/languages/adwptracker.pot`

2. **Create new translation**:
   - Click "Create New Translation"
   - Select your language (e.g., Spanish, German, etc.)

3. **Translate strings**:
   - Translate each English text to your language
   - Save regularly

4. **Export**:
   - Poedit automatically creates `.mo` file
   - Save as `adwptracker-{language_code}.po`
   - Example: `adwptracker-es_ES.po` for Spanish

5. **Install**:
   - Upload both `.po` and `.mo` files to `/languages/` folder
   - Change WordPress language setting

### Steps with Loco Translate Plugin:

1. **Install Loco Translate**:
   ```
   WordPress Admin → Plugins → Add New
   Search "Loco Translate" → Install → Activate
   ```

2. **Start Translation**:
   ```
   Loco Translate → Plugins → AdWPtracker
   Click "New Language"
   Select your language
   Click "Start Translating"
   ```

3. **Translate & Save**:
   - Translate strings in the interface
   - Click "Save" (automatically creates .mo file)

## Available Translations

Currently available:
- 🇬🇧 **English** (default)
- 🇫🇷 **French** (included)

Want to contribute a translation? Contact: support@adwptracker.com

## Translation Statistics

- Total translatable strings: ~150
- French translation: 100% complete
- Other languages: 0%

## For Developers: Adding Translations

All user-facing text uses `esc_html_e()` or `esc_html__()`:

```php
// Translatable
esc_html_e('Dashboard', 'adwptracker');

// Non-translatable (code, IDs, etc.)
echo 'dashboard';
```

To regenerate `.pot` file after code changes:

```bash
# Using WP-CLI
wp i18n make-pot . languages/adwptracker.pot

# Using Poedit
Poedit → File → New from POT/PO file → Select adwptracker.pot
```

## Troubleshooting

### Translation not showing?

1. **Check WordPress language**:
   - Settings → General → Site Language

2. **Check file names**:
   - Must match: `adwptracker-{locale}.mo`
   - French: `adwptracker-fr_FR.mo`
   - Spanish: `adwptracker-es_ES.mo`

3. **Check file location**:
   - Must be in: `/wp-content/plugins/adwptracker/languages/`

4. **Clear cache**:
   - Clear WordPress cache
   - Clear browser cache
   - Reload page

5. **Check .mo file exists**:
   - `.po` file alone won't work
   - Need compiled `.mo` file

### How to compile .po to .mo without Poedit?

```bash
# Using msgfmt (Linux/Mac)
msgfmt adwptracker-fr_FR.po -o adwptracker-fr_FR.mo

# Using online converter
# https://po2mo.net/
```

## Language Codes Reference

Common WordPress language codes:

| Language | Code |
|----------|------|
| English (US) | en_US |
| English (UK) | en_GB |
| French | fr_FR |
| Spanish | es_ES |
| German | de_DE |
| Italian | it_IT |
| Portuguese (Brazil) | pt_BR |
| Portuguese (Portugal) | pt_PT |
| Dutch | nl_NL |
| Russian | ru_RU |
| Arabic | ar |
| Chinese (Simplified) | zh_CN |
| Japanese | ja |

## Support

Need help with translations?

- 📧 Email: support@adwptracker.com
- 📖 Documentation: https://adwptracker.com/docs
- 💬 Forum: https://adwptracker.com/forum

## Contributing

Want to contribute a translation?

1. Create translation using Poedit
2. Test in WordPress
3. Send `.po` and `.mo` files to: translations@adwptracker.com
4. Get credited in plugin page!

---

**Thank you for using AdWPtracker!** 🚀
