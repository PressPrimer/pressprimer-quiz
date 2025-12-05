# PressPrimer Quiz

Enterprise-grade quiz and assessment system for WordPress

## Description

PressPrimer Quiz is a powerful and flexible quiz plugin designed for WordPress. It provides comprehensive features for creating, managing, and delivering quizzes and assessments.

## Features

- Create and manage quizzes
- Multiple question types support
- Quiz results tracking
- User progress monitoring
- Customizable quiz settings
- Responsive design

## Requirements

- WordPress 6.4 or higher
- PHP 7.4 or higher

## Installation

1. Upload the plugin files to the `/wp-content/plugins/pressprimer-quiz` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings screen to configure the plugin

## Development

### Directory Structure

```
pressprimer-quiz/
├── includes/          # Core plugin classes (PSR-4: PressPrimer\Quiz)
├── assets/           # CSS, JS, and image files
├── languages/        # Translation files
├── tests/           # PHPUnit tests
└── pressprimer-quiz.php  # Main plugin file
```

### Setup Development Environment

```bash
# Install dependencies
composer install

# Run tests
composer test
```

## License

This plugin is licensed under GPL v2 or later.

## Author

PressPrimer - [https://pressprimer.com](https://pressprimer.com)

## Version

0.1.0
