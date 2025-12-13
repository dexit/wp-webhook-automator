# Next Steps After Repository Push

## Immediate Actions

### 1. Fix GitHub Actions (if failed)
- Review failed workflows at: https://github.com/GhDj/wp-webhook-automator/actions
- Common issues:
  - Missing `bin/install-wp-tests.sh` script for CI tests
  - Missing PHPCS configuration (`.phpcs.xml`)
  - Composer scripts not defined (`phpcs`, `test`)

### 2. Add LICENSE File
Create a `LICENSE` file with GPL v2 text:
- Go to repository → Add file → Create new file
- Name it `LICENSE`
- GitHub will offer GPL-2.0 template

### 3. Add WordPress.org Assets
Add images to `.wordpress-org/` directory:

| File | Size | Description |
|------|------|-------------|
| `icon-128x128.png` | 128x128 | Plugin icon |
| `icon-256x256.png` | 256x256 | Plugin icon @2x |
| `banner-772x250.png` | 772x250 | Plugin banner |
| `banner-1544x500.png` | 1544x500 | Plugin banner @2x |
| `screenshot-1.png` | Any | Dashboard overview |
| `screenshot-2.png` | Any | Webhook list |
| `screenshot-3.png` | Any | Webhook editor |
| `screenshot-4.png` | Any | Payload builder |
| `screenshot-5.png` | Any | Log viewer |

---

## WordPress.org Submission

### Pre-Submission Checklist
- [ ] LICENSE file added
- [ ] readme.txt validates at https://wordpress.org/plugins/developers/readme-validator/
- [ ] All strings internationalized
- [ ] No external dependencies without consent
- [ ] No tracking without disclosure
- [ ] Secure code (sanitization, escaping, nonces)
- [ ] No pro upsells in admin (subtle links OK)

### Submission Process
1. Create account at https://wordpress.org/ (if not already)
2. Go to https://wordpress.org/plugins/developers/add/
3. Upload plugin zip (create from main branch without dev files)
4. Wait for review (1-7 days typically)
5. Address any feedback from reviewers
6. Once approved, plugin will be at: https://wordpress.org/plugins/wp-webhook-automator/

### After Approval - Add GitHub Secrets
Go to: Repository → Settings → Secrets and variables → Actions

| Secret | Value |
|--------|-------|
| `WPORG_SVN_USERNAME` | Your WordPress.org username |
| `WPORG_SVN_PASSWORD` | Your WordPress.org password |

---

## CI/CD Configuration Fixes

### Missing Files to Add

#### `bin/install-wp-tests.sh`
WordPress test suite installer script. Download from:
```bash
curl -o bin/install-wp-tests.sh https://raw.githubusercontent.com/wp-cli/scaffold-command/main/templates/install-wp-tests.sh
chmod +x bin/install-wp-tests.sh
```

#### `.phpcs.xml` (PHP CodeSniffer Config)
```xml
<?xml version="1.0"?>
<ruleset name="WP Webhook Automator">
    <description>Coding standards for WP Webhook Automator</description>

    <file>./src</file>
    <file>./includes</file>
    <file>./wp-webhook-automator.php</file>

    <exclude-pattern>/vendor/*</exclude-pattern>
    <exclude-pattern>/node_modules/*</exclude-pattern>
    <exclude-pattern>/tests/*</exclude-pattern>

    <arg name="extensions" value="php"/>
    <arg name="colors"/>
    <arg value="sp"/>

    <config name="testVersion" value="8.0-"/>

    <rule ref="WordPress">
        <exclude name="WordPress.Files.FileName.InvalidClassFileName"/>
        <exclude name="WordPress.Files.FileName.NotHyphenatedLowercase"/>
    </rule>

    <rule ref="WordPress.WP.I18n">
        <properties>
            <property name="text_domain" type="array">
                <element value="wp-webhook-automator"/>
            </property>
        </properties>
    </rule>
</ruleset>
```

### Composer Scripts to Add
Update `composer.json` to include:
```json
{
    "scripts": {
        "phpcs": "phpcs",
        "phpcbf": "phpcbf",
        "test": "phpunit",
        "test:unit": "phpunit --testsuite unit",
        "test:integration": "phpunit --testsuite integration",
        "test:coverage": "phpunit --coverage-html tests/coverage"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "brain/monkey": "^2.6",
        "mockery/mockery": "^1.6",
        "squizlabs/php_codesniffer": "^3.7",
        "wp-coding-standards/wpcs": "^3.0",
        "dealerdirect/phpcodesniffer-composer-installer": "^1.0"
    }
}
```

---

## Release Process

### Creating a New Release
1. Update version in:
   - `wp-webhook-automator.php` (Version header + WWA_VERSION constant)
   - `readme.txt` (Stable tag)

2. Update `CHANGELOG.md`

3. Commit and push:
   ```bash
   git add -A
   git commit -m "Bump version to X.X.X"
   git push origin main
   ```

4. Create and push tag:
   ```bash
   git tag -a vX.X.X -m "Release version X.X.X"
   git push origin vX.X.X
   ```

5. GitHub Actions will:
   - Build the plugin zip
   - Create GitHub Release
   - Deploy to WordPress.org (after secrets are configured)

---

## Pro Version Launch (Future)

### When Ready to Launch Pro
1. Update Freemius credentials in `includes/class-freemius.php`
2. Install dependencies: `composer install`
3. Test with free version
4. Create and push tag:
   ```bash
   git tag -a v1.0.0 -m "Release version 1.0.0"
   git push origin v1.0.0
   ```

### Freemius Account Setup
1. Sign up at https://freemius.com/
2. Add new plugin
3. Configure pricing plans
4. Get API credentials
5. Add GitHub secrets to private repo:
   - `FREEMIUS_DEV_ID`
   - `FREEMIUS_PLUGIN_ID`
   - `FREEMIUS_DEPLOY_KEY`
