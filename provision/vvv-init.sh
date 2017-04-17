#!/usr/bin/env bash

# Change to the parent directory to run scripts.
cd "${VM_DIR}"

# Some variables we'll need
WP_HOST=`get_primary_host`
WP_CONTENT=`get_config_value wp-content False`
NGINX_CONFIG_TEMPLATE="${VM_DIR}/provision/vvv-nginx.template"
NGINX_CONFIG_FILE="${VM_DIR}/provision/vvv-nginx.conf"
XIPIO_BASE=`echo ${WP_HOST} | sed -E 's#(.*)\.[a-zA-Z0-9_]+$#\1#'`
NGINX_XIPIO="~^${XIPIO_BASE/./\\\\.}\\\\.\\\\d+\\\\.\\\\d+\\\\.\\\\d+\\\\.\\\\d+\\\\.xip\\\\.io$" # Lots of extra slashes to survive until the final file

# Run composer
noroot composer install

# Make a database, if we don't already have one
echo -e "\n\nCreating database '${SITE_ESCAPED}' (if it's not already there)"
mysql -u root --password=root -e "CREATE DATABASE IF NOT EXISTS \`${SITE_ESCAPED}\`"
mysql -u root --password=root -e "GRANT ALL PRIVILEGES ON \`${SITE_ESCAPED}\`.* TO wp@localhost IDENTIFIED BY 'wp';"
echo -e "\nDB operations done.\n"

# Nginx Logs
echo -e "\nCreating log files"
mkdir -p ${VVV_PATH_TO_SITE}/log
touch ${VVV_PATH_TO_SITE}/log/error.log
touch ${VVV_PATH_TO_SITE}/log/access.log
echo -e "\nLog files done."

# Maybe remove default wp-content
if [[ False != ${WP_CONTENT} ]]; then
	echo -e "\nSetting up wp-content..."
	noroot composer remove "wordpress/wordpress"
	noroot composer require "wordpress/wordpress-no-content"
	noroot composer update

	echo -e"\nCloning the wp-content repo..."
	noroot git clone --recursive "${WP_CONTENT}"
	echo -e"\nwp-content done."
fi


# Maybe install WordPress
if ! noroot wp core is-installed; then
	echo -e "Installing WordPress...\n\n"

	WP_ADMIN_USER=`get_config_value admin_user 'admin'`
	WP_ADMIN_PASS=`get_config_value admin_password 'password'`
	WP_ADMIN_EMAIL=`get_config_value admin_email 'admin@localhost.dev'`
	WP_SITE_TITLE=`get_config_value title 'My Awesome Site'`

	noroot wp core config --force --dbname="${SITE_ESCAPED}" --dbuser=wp --dbpass=wp --dbhost="localhost" --dbprefix=wp_ --locale=en_US --extra-php <<PHP
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', true );
define( 'SCRIPT_DEBUG', true );
define( 'JETPACK_DEV_DEBUG', true );
PHP

	noroot wp core install --url="${WP_HOST}" --title="${WP_SITE_TITLE}" --admin_user="${WP_ADMIN_USER}" --admin_password="${WP_ADMIN_PASS}" --admin_email="${WP_ADMIN_EMAIL}"
fi

# Add domains to the Nginx config
sed "s#{wp_server_names}#`get_hosts` ${NGINX_XIPIO}#" "${NGINX_CONFIG_TEMPLATE}" > "${NGINX_CONFIG_FILE}"

# Return to the previous directory
cd -
