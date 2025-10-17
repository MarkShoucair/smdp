-- Quick SQL query to check if access token is encrypted
-- Run this in phpMyAdmin or MySQL client

-- Replace 'wp_' with your actual table prefix if different
SELECT
    option_name,
    SUBSTRING(option_value, 1, 100) as 'first_100_chars',
    LENGTH(option_value) as 'total_length',
    CASE
        WHEN option_value LIKE 'sq0%' THEN '✗ PLAIN TEXT (Sandbox)'
        WHEN option_value LIKE 'EAAA%' THEN '✗ PLAIN TEXT (Production)'
        WHEN LENGTH(option_value) > 100 THEN '✓ Appears Encrypted'
        ELSE '? Unknown Format'
    END as 'security_status'
FROM wp_options
WHERE option_name = 'square_menu_access_token';
