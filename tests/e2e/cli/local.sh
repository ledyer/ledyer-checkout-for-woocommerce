wp option update siteurl http://127.0.0.1:8000
wp option update home http://127.0.0.1:8000
wp search-replace 'http://wordpress' 'http://127.0.0.1:8000' --all-tables