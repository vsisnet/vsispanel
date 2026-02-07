# VSISPanel SSO Plugin for Roundcube

This plugin enables Single Sign-On (SSO) from VSISPanel to Roundcube webmail.

## Installation

1. Copy the `vsispanel_sso` folder to your Roundcube plugins directory:
   ```bash
   cp -r vsispanel_sso /var/www/roundcube/plugins/
   ```

2. Add the plugin to your Roundcube configuration (`config/config.inc.php`):
   ```php
   $config['plugins'] = array(
       // ... other plugins
       'vsispanel_sso',
   );
   ```

3. Configure the plugin by adding these settings to `config/config.inc.php`:
   ```php
   // VSISPanel SSO Configuration
   $config['vsispanel_api_url'] = 'http://your-panel-url/api';
   $config['vsispanel_api_key'] = 'your-api-key-here';
   ```

## How It Works

1. When a user clicks "Open Webmail" in VSISPanel, a temporary SSO token is generated
2. The user is redirected to Roundcube with the token in the URL
3. This plugin intercepts the token and validates it with VSISPanel's API
4. If valid, the user is automatically logged in

## Security Notes

- SSO tokens are single-use by default
- Tokens expire after 5 minutes
- IP validation can be enabled for additional security
- All communication should use HTTPS in production

## Configuration Options

| Option | Description | Default |
|--------|-------------|---------|
| `vsispanel_api_url` | VSISPanel API base URL | `http://localhost:8000/api` |
| `vsispanel_api_key` | API key for authentication | Required |

## Troubleshooting

Check the Roundcube logs at `logs/vsispanel_sso` for debugging information.

## License

MIT License - See LICENSE file for details.
