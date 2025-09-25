# PROMPT_EXTRA heredoc update regression

This scenario covers updating the `PROMPT_EXTRA` configuration value when it uses a heredoc/nowdoc
containing semicolons. Earlier versions of the admin panel truncated the assignment because the
search/replace stopped at the first semicolon.

## Reproduction steps

1. Create a minimal config file `config.test.php` with a heredoc value that includes semicolons:
   ```php
   <?php
   $PROMPT_EXTRA = <<<TXT
   Line one;
   Line two;
   TXT;
   ```

2. Run the updater with a replacement string that also includes semicolons:
   ```bash
   php -r 'require __DIR__ . "/core/admin.php"; $errors = []; admin_update_config_values(__DIR__ . "/config.test.php", ["PROMPT_EXTRA" => "Updated; value"], $errors); var_export($errors);'
   ```

3. Inspect `config.test.php`. The `$PROMPT_EXTRA` assignment should now read:
   ```php
   $PROMPT_EXTRA = 'Updated; value';
   ```
   and the file should contain valid PHP syntax without truncated heredoc blocks.

4. (Optional) Validate the result with `php -l config.test.php` to confirm the file remains
   syntactically valid.

These steps confirm that the updater replaces the full heredoc block instead of stopping at the
first semicolon.
