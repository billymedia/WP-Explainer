# WP AI Explainer for WordPress

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.8-orange.svg)](https://github.com/billymedia/WP-AI-Explainer/releases)

A lightweight WordPress plugin that transforms your website into an interactive learning experience. Users can simply select any text and receive instant AI-generated explanations via elegant, customisable tooltips.

## Demo Video

Watch a quick demo of WP AI Explainer in action:

![WP AI Explainer Demo](assets/demo.gif)

## Key Features

- **Multi-Provider AI Support**: Choose between OpenAI (GPT-3.5, GPT-4) and Claude (Sonnet, Haiku) models
- **Interactive Tooltips**: Smart positioning with manual user control (no auto-close)
- **Multi-Language Support**: Localised tooltips in 7 languages (English US/UK, Spanish, German, French, Hindi, Chinese)
- **Fully Customisable**: Configure tooltip colours (background, text, footer), positioning, disclaimers, and provider attribution
- **Secure Integration**: API keys encrypted with WordPress salts and never exposed to frontend
- **Advanced Admin Interface**: Tabbed settings with Basic and Advanced configuration options
- **Custom Prompt Templates**: Create personalised AI prompts with `{{snippet}}` placeholders
- **Smart Content Filtering**: Configurable text selection rules and content exclusions
- **Performance Optimised**: Intelligent caching, rate limiting, and minimal page load impact (<100ms)
- **Theme Compatible**: Responsive design that works seamlessly with any WordPress theme
- **Accessibility First**: WCAG AA compliant with full keyboard navigation and screen reader support
- **Mobile Optimised**: Touch-friendly interface with swipe gestures for all devices

## Perfect For

- **Educational Websites**: Online courses, tutorials, and learning platforms
- **Technical Documentation**: Knowledge bases, developer docs, and API references
- **News & Publishing**: Magazine sites, blogs, and content-heavy websites
- **Professional Services**: Sites with complex terminology and jargon
- **E-commerce**: Product descriptions and detailed specifications
- **Any Website**: With content that benefits from explanations

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **API Key**: From OpenAI or Claude (Anthropic)

## Installation

<!--
### From WordPress Admin

1. Go to **Plugins → Add New**
2. Search for "WP AI Explainer"
3. Click **Install Now** and then **Activate**
4. Navigate to **Settings → Explainer Settings**
5. Choose your AI provider (OpenAI or Claude)
6. Select your preferred language for tooltip interface
7. Enter your API key and select a model
8. Test your API key to ensure it works
9. Customise appearance and footer options
10. Configure advanced settings as needed
11. Save your settings and test on your site
-->
### From WordPress Admin
Currently under review.

### Manual Installation

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu
4. Configure settings as described above

### Getting API Keys

#### For OpenAI
1. Visit [platform.openai.com](https://platform.openai.com/)
2. Create an account or log in
3. Navigate to API Keys section
4. Create a new secret key
5. Copy and paste into plugin settings

#### For Claude (Anthropic)
1. Visit [console.anthropic.com](https://console.anthropic.com/)
2. Create an account or log in
3. Navigate to API Keys section
4. Generate a new API key
5. Copy and paste into plugin settings

## How It Works

1. **Install & Configure**: Set up the plugin with your preferred AI provider and language
2. **Customise Appearance**: Match your site's design with custom colours
3. **Users Select Text**: Visitors highlight any text on your website
4. **AI Explains**: Instant explanations appear in localised responsive tooltips


## Configuration Options

### Basic Settings Tab
- **Plugin Status**: Enable/disable functionality site-wide
- **Language Selection**: Choose from 7 supported languages for tooltip interface
- **AI Provider**: Choose between OpenAI or Claude
- **API Keys**: Secure, encrypted storage for your API credentials
- **AI Models**: Select from available models (GPT-3.5, GPT-4, Claude variants)
- **Custom Prompts**: Personalise AI prompts with template variables

### Appearance Tab
- **Tooltip Colours**: Background, text, and footer text colour customisation
- **Button Colours**: Toggle button appearance settings
- **Position**: Choose where the toggle button appears
- **Footer Options**: Configure disclaimers and provider attribution
- **Live Preview**: See changes in real-time

### Content Rules Tab
- **Text Limits**: Set minimum and maximum selection lengths
- **Word Limits**: Control word count parameters
- **CSS Selectors**: Include/exclude specific page elements
- **Custom Prompts**: Advanced prompt engineering with validation

### Performance Tab
- **Caching**: Reduce API costs with intelligent response caching
- **Rate Limiting**: Prevent abuse with configurable limits
- **User Controls**: Different limits for logged-in vs anonymous users

#### Understanding Rate Limiting

Rate limiting controls how many explanation requests users can make within specific time periods to prevent abuse and manage API costs.

**How It Works:**
- **Time Windows**: Rate limits reset every minute (60-second sliding windows)
- **Per-User Tracking**: Each logged-in user or anonymous visitor has their own counter
- **Cache Exclusion**: Cached explanations don't count toward rate limits
- **Multiple Tiers**: Minute (primary), hour, and daily limits for comprehensive protection

**Default Limits:**
- **Logged-in users**: 20 requests per minute, 100 per hour, 500 per day
- **Anonymous users**: 10 requests per minute, 50 per hour, 200 per day
- **Rate limiting**: Enabled by default (configurable in Performance tab)

**Common Scenarios:**
- *24 cached items but no rate limiting triggered*: Cached items accumulate over days/weeks, but rate limits only count fresh API requests within 60-second windows
- *Testing rate limits*: Make 17+ unique requests (different text) within 60 seconds to see "Rate limit exceeded" message
- *Different user messages*: Plugin rate limiting shows "Please wait before making another request", while AI provider limits show "Please try again later"

**Best Practices:**
- Start with conservative limits (10-20 per minute) and adjust based on usage
- Enable caching to reduce API calls and extend effective limits
- Monitor your AI provider's dashboard for API usage patterns
- Set different limits for logged-in users vs anonymous visitors based on trust level

### Advanced Tab
- **Debug Tools**: Comprehensive logging and troubleshooting
- **Security Options**: Enhanced validation and sanitisation
- **Performance Tuning**: Fine-tune for optimal performance

## Bug Reports & Feature Requests

### Reporting Issues
- **GitHub Issues**: [github.com/billymedia/WP-AI-Explainer/issues](https://github.com/billymedia/WP-AI-Explainer/issues)
- Include WordPress version, PHP version, and detailed steps to reproduce
- Enable Debug Mode for detailed error logs
- Check existing issues before creating new ones

### Custom Modifications
- **Professional Services**: Contact Billy directly at [billy@billymedia.co.uk](mailto:billy@billymedia.co.uk)
- **Custom Development**: Available for bespoke modifications and integrations

### Coding Standards
- Follow WordPress PHP and JavaScript coding standards
- Use proper sanitisation and validation
- Include inline documentation
- Test on WordPress 5.0+ and PHP 7.4+
- Ensure accessibility compliance (WCAG AA)

## Changelog

##### Version 1.0.9 *(July 24, 2025)*
- Enhanced error popup styling with white text, headers, and close buttons for better visibility
- Comprehensive rate limiting documentation added to admin interface and help system
- Improved inline help explaining how rate limiting works with time windows and cache exclusion
- Added detailed rate limiting section to help tab with common questions and troubleshooting
- Updated README with comprehensive rate limiting explanation and best practices

##### Version 1.0.8 *(July 24, 2025)*
- Enhanced debug logging with comprehensive API request flow tracking
- Improved tab persistence using localStorage for better user experience
- Moved Reset to Defaults button to Advanced tab for better organisation
- Relocated Test API Key buttons next to API key inputs, removed Quick Actions section
- Enhanced security by preventing API key transmission in client requests
- Fixed tooltip preview horizontal line styling to match frontend appearance
- Updated help, support, and readme content to be more conversational and human-sounding
- Reduced debug logging API key prefix from 10 to 3 characters for improved security
- Comprehensive security audit ensuring API keys never appear in POST/GET requests
- Added working iframe display functionality
- Integrated YouTube demo video
- Removed default exclusion classes for better theme compatibility

##### Version 1.0.6 *(July 20, 2025)*
- WordPress Plugin Directory compliance improvements
- Enhanced plugin validation and quality checks
- Code structure improvements for WordPress standards

##### Version 1.0.5 *(July 19, 2025)*
- Updated repository links to correct GitHub location
- Fixed documentation references and URLs

##### Version 1.0.4 *(July 19, 2025)*
- Added correct UK English language support
- Improved British English localisation accuracy

##### Version 1.0.3 *(July 19, 2025)*
- Major restructure and rename of plugin files and folders
- Improved file organisation and naming conventions
- Enhanced code maintainability

##### Version 1.0.2 *(July 19, 2025)*
- Added blocked words filter functionality
- Content filtering and moderation capabilities
- Removed test code and cleaned up codebase

##### Version 1.0.1 *(July 19, 2025)*
- Added API usage monitoring and automatic plugin disable on quota exceeded
- Enhanced error handling for API limits
- Improved cost management features

##### Version 1.0.0 *(July 19, 2025)*
- Multi-Provider Support: OpenAI and Claude integration with model selection
- Multi-Language Interface: Localised tooltips in 7 languages with real-time language switching
- Comprehensive Admin Interface: Tabbed settings with Basic and Advanced options
- Custom Prompt Templates: Personalised AI prompts with validation
- Encrypted API Storage: Secure WordPress salts-based encryption
- Provider Factory Architecture: Clean, extensible codebase for future providers
- Enhanced Security: Comprehensive validation, sanitisation, and capability checks
- Performance Optimisation: Intelligent caching, rate limiting, and minimal impact
- Accessibility Excellence: WCAG AA compliance with full keyboard and screen reader support
- Mobile Excellence: Touch-friendly interface with responsive design
- Theme Compatibility: Works with any WordPress theme
- Debug Tools: Comprehensive logging and troubleshooting capabilities

## Privacy & Security

### Data Handling
- **API Keys**: Encrypted using WordPress salts, never exposed to frontend
- **User Content**: Only selected text sent to AI providers for processing
- **No Tracking**: No personal data collection or tracking cookies
- **GDPR Compliant**: Users control when explanations are requested

### Security Features
- WordPress nonces for all AJAX requests
- Capability checks for admin access
- Input sanitisation
- Rate limiting via WordPress transients
- Secure API proxy prevents key exposure

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) file for details.

### Third-Party Services
- **OpenAI**: [Terms](https://openai.com/terms/) | [Privacy](https://openai.com/privacy/)
- **Anthropic Claude**: [Terms](https://www.anthropic.com/terms) | [Privacy](https://www.anthropic.com/privacy)

## Support & Contact

- **GitHub Issues**: [Report bugs or request features](https://github.com/billymedia/WP-AI-Explainer/issues)
- **Custom Development**: [billy@billymedia.co.uk](mailto:billy@billymedia.co.uk)
- **Documentation**: Available in the WordPress admin Help tab

---
