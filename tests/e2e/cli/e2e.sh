wp option update siteurl http://${SITEHOST}
wp option update home http://${SITEHOST}
wp search-replace "http://127.0.0.1:8000" "http://${SITEHOST}" --all-tables
wp search-replace "http://localhost" "http://${SITEHOST}" --all-tables