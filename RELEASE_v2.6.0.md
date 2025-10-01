# Release Notes - v2.6.0

## Version Information
- **Version**: 2.6.0
- **Release Date**: September 30, 2025
- **Branch**: main
- **Commit**: aa4b8e1
- **Tag**: v2.6.0

## New Features

### 1. Mistral AI Provider
- ✅ New `MistralProvider` class extending OpenAI compatibility
- ✅ Full support for Mistral AI API (https://api.mistral.ai)
- ✅ Chat, streaming, and embeddings capabilities
- ✅ Configuration via `MISTRAL_API_KEY` environment variable
- ✅ Optional endpoint override with `MISTRAL_ENDPOINT`

### 2. Enhanced Examples
- ✅ `examples/mistral_smoke.php` - Dedicated Mistral test script
- ✅ `examples/multi_provider_smoke.php` - Test multiple providers in one script
- ✅ Improved `examples/openrouter_smoke.php` with better error handling
- ✅ All examples now include:
  - API key validation with helpful error messages
  - PowerShell-friendly setup instructions
  - Try/catch error handling
  - Exit codes for CI/CD integration

### 3. Documentation Updates
- ✅ Updated README.md with Mistral AI section
- ✅ Updated CHANGELOG.md with v2.6.0 release notes
- ✅ Enhanced configuration examples
- ✅ Added Mistral to supported providers table

## Files Modified
- `CHANGELOG.md` - Added v2.6.0 release notes
- `README.md` - Added Mistral documentation and configuration
- `config/AiBridge.php` - Added Mistral configuration section
- `src/AiBridgeManager.php` - Added Mistral provider support
- `src/Providers/MistralProvider.php` - NEW FILE
- `examples/mistral_smoke.php` - NEW FILE
- `examples/multi_provider_smoke.php` - NEW FILE
- `examples/openrouter_smoke.php` - Enhanced with error handling
- `examples/ollama_turbo_smoke.php` - Enhanced with error handling

## Supported Providers (Updated)
| Provider | Chat | Stream | Embeddings | Images | Audio (TTS) | Audio (STT) | Tools |
|----------|------|--------|------------|--------|-------------|-------------|-------|
| **OpenAI** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Ollama** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ |
| **Ollama Turbo** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ |
| **Mistral** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ |
| **Gemini** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ |
| **Claude** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Grok** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **OpenRouter** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **ONN** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Custom OpenAI** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

## Installation & Usage

### Install/Update via Composer
```bash
composer require omgbwa-yasse/aibridge
```

### Configure Mistral AI
```env
MISTRAL_API_KEY=your-mistral-key-here
# Optional: MISTRAL_ENDPOINT=https://api.mistral.ai/v1/chat/completions
```

### Test the Provider
```powershell
# PowerShell
$env:MISTRAL_API_KEY = "your-key-here"
php examples/mistral_smoke.php

# Test multiple providers
$env:MISTRAL_API_KEY = "mistral-key"
$env:OPENROUTER_API_KEY = "openrouter-key"
$env:OLLAMA_TURBO_API_KEY = "ollama-key"
php examples/multi_provider_smoke.php
```

## Publishing to Packagist

### 1. Push to GitHub
```bash
# Push the commit
git push origin main

# Push the tag
git push origin v2.6.0
```

### 2. Packagist Auto-Update
Packagist should automatically detect the new tag if the GitHub webhook is configured.

### 3. Manual Update (if needed)
1. Go to https://packagist.org/packages/omgbwa-yasse/aibridge
2. Click "Update" button
3. Verify v2.6.0 appears in the versions list

## Testing Checklist
- [x] PHP syntax check on all modified files
- [x] Mistral provider class created and extends OpenAIProvider
- [x] Configuration added to config/AiBridge.php
- [x] Manager updated to support Mistral
- [x] Examples created and tested (validation without API keys)
- [x] README updated with Mistral documentation
- [x] CHANGELOG updated with release notes
- [x] Git commit created
- [x] Git tag v2.6.0 created
- [ ] Push to GitHub
- [ ] Verify Packagist update
- [ ] Test installation from Packagist

## Quick Test Commands
```powershell
# Verify no syntax errors
php -l examples/mistral_smoke.php
php -l examples/multi_provider_smoke.php
php -l src/Providers/MistralProvider.php

# Test examples (should show helpful error messages without API keys)
php examples/mistral_smoke.php
php examples/openrouter_smoke.php
php examples/multi_provider_smoke.php
```

## Next Steps
1. Execute: `git push origin main`
2. Execute: `git push origin v2.6.0`
3. Verify on GitHub: https://github.com/omgbwa-yasse/AiBridge/releases
4. Create GitHub Release (optional): Add release notes from CHANGELOG.md
5. Verify on Packagist: https://packagist.org/packages/omgbwa-yasse/aibridge
6. Test installation in a fresh project:
   ```bash
   composer require omgbwa-yasse/aibridge:^2.6
   ```

## Breaking Changes
None - This is a backward-compatible feature release.

## Migration Guide
No migration needed. Existing code continues to work unchanged.

To use the new Mistral provider:
1. Add `MISTRAL_API_KEY` to your `.env`
2. Use `AiBridge::chat('mistral', $messages, ['model' => 'mistral-small-latest'])`

## Support
- Documentation: See README.md
- Issues: https://github.com/omgbwa-yasse/AiBridge/issues
- Examples: See examples/ directory
