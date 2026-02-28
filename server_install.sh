#!/bin/bash
# pretty much
# sudo bash server_install.sh
# disable cloudflare proxy
# sudo apt update && sudo apt install ufw -y
# sudo nano /etc/ssh/sshd_config
# --- change sshd port 22 to 8888
# sudo ufw default deny incoming
# sudo ufw default allow outgoing
# sudo ufw allow 8888/tcp
# sudo ufw enable
# --- should be ready
# sudo apt install git
# git clone <karachan>
# fix nginx config. fastcgi_pass unix:/run/php/php8.4-fpm.sock;
# after running all the steps and getting the cert do:
# sudo ufw status numbered
# and delete port 80/443 rules (sudo ufw delete 2), then:
# sudo bash cloudflare_hack.sh
# enable cloudflare fully

set -e

# config vars
MYSQL_ROOT_PASSWORD="coG6AGYEYuReAKKmFejxrydz"
MYSQL_DATABASE="karachan"
MYSQL_USER="elias"
MYSQL_PASSWORD="XfYJl0bi7YPT79OYTu4BDhRb"
TARGET_LOCATION="/var/www/html"
DISCORD_WEBHOOK_MOD="https://discord.com/api/webhooks/1399668665896534037/3Jxf77XckpCzSOGN4qiAzq7M_-62Uci0TZeVOF9E8R8zeTAXT7zr5IfphqGZ2PaWHubB"
DISCORD_WEBHOOK_POSTS="https://discord.com/api/webhooks/1422339740702867658/0gwov4bNXIATNBAoVCssQB5DVAFOZNBurQDmW3o0edQkJdqA0V-jC1kygwXN_BIqo3m2"
KARACHAN_DOMAIN="https://avackusniche.org"

# php version check
if command -v php >/dev/null 2>&1; then
	php_version=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
else
	echo "php not found, skipping version check~"
	php_version=""
fi

fix_config_php_variables() {
	sudo sed -i \
		-e "s|\$config\['db'\]\['server'\] = 'db';|\$config['db']['server'] = '127.0.0.1';|" \
		-e "s|\$config\['debug'\] = true;|\$config['debug'] = false;|" \
		-e "s|\$config\['db'\]\['password'\] = 'stinkybutt';|\$config['db']['password'] = '${MYSQL_PASSWORD}';|" \
		-e "s|\$config\['discord_webhook_moderation'\] = '.*';|\$config['discord_webhook_moderation'] = '${DISCORD_WEBHOOK_MOD}';|" \
		-e "s|\$config\['discord_webhook_posts'\] = '.*';|\$config['discord_webhook_posts'] = '${DISCORD_WEBHOOK_POSTS}';|" \
		-e "s|\$config\['domain'\] = '.*';|\$config['domain'] = '${KARACHAN_DOMAIN}';|" \
		"$TARGET_LOCATION/inc/config.php"
}

# step 1, packages
if [[ "$1" == "-step1" ]]; then
	echo "[*] installing deps~"
	sudo apt update
	sudo apt install -y lsb-release apt-transport-https ca-certificates wget curl gnupg

	# add the sury gpg key
	curl -fsSL https://packages.sury.org/php/apt.gpg | sudo gpg --dearmor -o /etc/apt/trusted.gpg.d/php.gpg

	# add the repo
	echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/php.list

	sudo apt update
	sudo apt install -y php-fpm php-cli php-mysql php-bcmath php-mbstring php-xml php-zip php-gd php-imagick libmagickwand-dev imagemagick redis php-redis nginx mariadb-server git unzip curl composer
fi

# step 2, install mysql
if [[ "$1" == "-step2" ]]; then
	echo "[*] setting up mysql bruh~"
	sudo systemctl enable mariadb
	sudo systemctl start mariadb

sudo mysql <<EOF
ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
CREATE DATABASE IF NOT EXISTS ${MYSQL_DATABASE};
CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'localhost' IDENTIFIED BY '${MYSQL_PASSWORD}';
GRANT ALL PRIVILEGES ON ${MYSQL_DATABASE}.* TO '${MYSQL_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
fi

# step 3, php check
if [[ "$1" == "-step3" ]]; then
	echo "Current PHP version is: $php_version"
fi

# step 4, php setup
if [[ "$1" == "-step4" ]]; then
	echo "[*] configuring php$php_version-fpm~"
	sudo sed -i 's/^;*expose_php.*/expose_php = Off/' /etc/php/$php_version/fpm/php.ini
	sudo sed -i 's/^ping.path.*/; ping.path = \/ping/' /etc/php/$php_version/fpm/pool.d/www.conf
	sudo sed -i 's/^pm.status_path.*/; pm.status_path = \/status/' /etc/php/$php_version/fpm/pool.d/www.conf
	sudo sed -i '/^;extension=imagick.so/s/^;//' /etc/php/$php_version/fpm/php.ini
	sudo sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 10M/' /etc/php/$php_version/fpm/php.ini
	sudo sed -i 's/^post_max_size = .*/post_max_size = 10M/' /etc/php/$php_version/fpm/php.ini
	sudo systemctl restart php$php_version-fpm
fi

if [[ "$1" == "-step5" ]]; then
	echo "[*] configuring nginx~"
	sudo mkdir -p $TARGET_LOCATION
	sudo cp -r ./karachan/* $TARGET_LOCATION/

	# fix configuration
	fix_config_php_variables

	sudo cp ./nginx/default.conf /etc/nginx/sites-available/karachan
	sudo ln -sf /etc/nginx/sites-available/karachan /etc/nginx/sites-enabled/

	# fix permissions
	sudo chown -R www-data:www-data $TARGET_LOCATION

	# restart shit
	echo "[*] restarting nginx~"
	sudo systemctl restart nginx
	sudo systemctl enable nginx
fi

if [[ "$1" == "-step6" ]]; then
	echo "[*] installing composer deps~"
	cd $TARGET_LOCATION
	sudo -u www-data composer install
fi

if [[ "$1" == "-step7" ]]; then
	# let's encrypt cert
	echo "[*] installing certbot + nginx plugin~"
	sudo apt install -y certbot python3-certbot-nginx

	echo "[*] getting SSL cert from Let's Encrypt~"
	sudo certbot --nginx --non-interactive --agree-tos --redirect -m thedabbers@protonmail.com -d avackusniche.org -d www.avackusniche.org

	echo "[*] setting up auto renewal cron~"
	sudo systemctl enable certbot.timer

	echo "[✓] all done bro~ it's cookin on :80~"
fi

if [[ "$1" == "-update" ]]; then
	sudo systemctl stop nginx
	sudo systemctl stop php$php_version-fpm

	# paths
	SRC="./karachan"

	# folders to replace
	DIRS=("assets" "templates" "inc")

	# files to replace
	FILES=("banned.php" "composer.json" "favicon.ico" "install.php" "install.sql" "account.php" "kcindex.php" "post.php")

	# remove old dirs
	for dir in "${DIRS[@]}"; do
		if [ -d "$TARGET_LOCATION/$dir" ]; then
			echo "removing dir: $TARGET_LOCATION/$dir"
			sudo rm -rf "$TARGET_LOCATION/$dir"
		fi
	done

	# copy fresh dirs
	for dir in "${DIRS[@]}"; do
		if [ -d "$SRC/$dir" ]; then
			echo "copying dir: $SRC/$dir -> $TARGET_LOCATION/$dir"
			sudo cp -r "$SRC/$dir" "$TARGET_LOCATION/"
		fi
	done

	# remove old files
	for file in "${FILES[@]}"; do
		if [ -f "$TARGET_LOCATION/$file" ]; then
			echo "removing file: $TARGET_LOCATION/$file"
			sudo rm -f "$TARGET_LOCATION/$file"
		fi
	done

	# copy fresh files
	for file in "${FILES[@]}"; do
		if [ -f "$SRC/$file" ]; then
			echo "copying file: $SRC/$file -> $TARGET_LOCATION/$file"
			sudo cp "$SRC/$file" "$TARGET_LOCATION/"
		fi
	done

	# fix configuration
	fix_config_php_variables

	# bring it online
	sudo systemctl start php$php_version-fpm
	sudo systemctl start nginx
fi

if [[ "$1" == "-rebuildcomposer" ]]; then
	# composer has to be rebuilt
	cd $TARGET_LOCATION
	sudo rm -rf "$TARGET_LOCATION/vendor"
	sudo -u www-data composer install
fi

if [[ "$1" == "-cfhack" ]]; then
	sudo ufw allow from 103.21.244.0/22 to any port 80 proto tcp
	sudo ufw allow from 103.21.244.0/22 to any port 443 proto tcp
	sudo ufw allow from 103.22.200.0/22 to any port 80 proto tcp
	sudo ufw allow from 103.22.200.0/22 to any port 443 proto tcp
	sudo ufw allow from 103.31.4.0/22 to any port 80 proto tcp
	sudo ufw allow from 103.31.4.0/22 to any port 443 proto tcp
	sudo ufw allow from 104.16.0.0/13 to any port 80 proto tcp
	sudo ufw allow from 104.16.0.0/13 to any port 443 proto tcp
	sudo ufw allow from 104.24.0.0/14 to any port 80 proto tcp
	sudo ufw allow from 104.24.0.0/14 to any port 443 proto tcp
	sudo ufw allow from 108.162.192.0/18 to any port 80 proto tcp
	sudo ufw allow from 108.162.192.0/18 to any port 443 proto tcp
	sudo ufw allow from 131.0.72.0/22 to any port 80 proto tcp
	sudo ufw allow from 131.0.72.0/22 to any port 443 proto tcp
	sudo ufw allow from 141.101.64.0/18 to any port 80 proto tcp
	sudo ufw allow from 141.101.64.0/18 to any port 443 proto tcp
	sudo ufw allow from 162.158.0.0/15 to any port 80 proto tcp
	sudo ufw allow from 162.158.0.0/15 to any port 443 proto tcp
	sudo ufw allow from 172.64.0.0/13 to any port 80 proto tcp
	sudo ufw allow from 172.64.0.0/13 to any port 443 proto tcp
	sudo ufw allow from 173.245.48.0/20 to any port 80 proto tcp
	sudo ufw allow from 173.245.48.0/20 to any port 443 proto tcp
	sudo ufw allow from 188.114.96.0/20 to any port 80 proto tcp
	sudo ufw allow from 188.114.96.0/20 to any port 443 proto tcp
	sudo ufw allow from 190.93.240.0/20 to any port 80 proto tcp
	sudo ufw allow from 190.93.240.0/20 to any port 443 proto tcp
	sudo ufw allow from 197.234.240.0/22 to any port 80 proto tcp
	sudo ufw allow from 197.234.240.0/22 to any port 443 proto tcp
	sudo ufw allow from 198.41.128.0/17 to any port 80 proto tcp
	sudo ufw allow from 198.41.128.0/17 to any port 443 proto tcp
	sudo ufw allow from 2400:cb00::/32 to any port 80 proto tcp
	sudo ufw allow from 2400:cb00::/32 to any port 443 proto tcp
	sudo ufw allow from 2606:4700::/32 to any port 80 proto tcp
	sudo ufw allow from 2606:4700::/32 to any port 443 proto tcp
	sudo ufw allow from 2803:f800::/32 to any port 80 proto tcp
	sudo ufw allow from 2803:f800::/32 to any port 443 proto tcp
	sudo ufw allow from 2405:b500::/32 to any port 80 proto tcp
	sudo ufw allow from 2405:b500::/32 to any port 443 proto tcp
	sudo ufw allow from 2405:8100::/32 to any port 80 proto tcp
	sudo ufw allow from 2405:8100::/32 to any port 443 proto tcp
	sudo ufw allow from 2a06:98c0::/29 to any port 80 proto tcp
	sudo ufw allow from 2a06:98c0::/29 to any port 443 proto tcp
	sudo ufw allow from 2c0f:f248::/32 to any port 80 proto tcp
	sudo ufw allow from 2c0f:f248::/32 to any port 443 proto tcp
fi