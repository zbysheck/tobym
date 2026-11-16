#!/usr/bin/env bash
# Jednorazowa konfiguracja stagingu na MyDevil. Uruchamiany przez deploy\setup.bat.
# Mozna odpalac wielokrotnie: pomija kroki, ktore sa juz zrobione.
set -euo pipefail

DOMAIN=tobym.zbigniewkisly.usermd.net
REPO=git@github.com:zbysheck/tobym.git
KEY=$HOME/.ssh/tobym_deploy
DIR=$HOME/domains/$DOMAIN
# Znajduje Composera 2 (na MyDevil bywa pod roznymi nazwami); w ostatecznosci pobiera composer.phar.
find_composer() {
    local c
    for c in composer2 composer /usr/local/bin/composer2 /usr/local/bin/composer "$HOME/bin/composer"; do
        if command -v "$c" >/dev/null 2>&1 && "$c" --version 2>/dev/null | grep -Eq 'version 2\.'; then
            echo "$c"
            return
        fi
    done
    mkdir -p "$HOME/bin"
    if [ ! -f "$HOME/bin/composer.phar" ]; then
        php -r 'copy("https://getcomposer.org/download/latest-2.x/composer.phar", getenv("HOME")."/bin/composer.phar") || exit(1);' >&2 || return 1
    fi
    echo "php $HOME/bin/composer.phar"
}
COMPOSER=$(find_composer)

echo "== 1/6 Strona WWW =="
if [ ! -d "$DIR" ]; then
    devil www add "$DOMAIN" php || {
        echo "Nie udalo sie dodac strony. Dodaj $DOMAIN (typ PHP) w panelu MyDevil i uruchom setup ponownie."
        exit 1
    }
fi

echo "== 2/6 Klucz do repozytorium =="
mkdir -p "$HOME/.ssh" && chmod 700 "$HOME/.ssh"
[ -f "$KEY" ] || ssh-keygen -q -t ed25519 -f "$KEY" -N "" -C tobym-deploy
export GIT_SSH_COMMAND="ssh -i $KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"
if [ ! -d "$DIR/app/.git" ]; then
    until git ls-remote "$REPO" >/dev/null 2>&1; do
        echo
        echo "GitHub nie przyjmuje jeszcze klucza. Dodaj go: repo tobym > Settings > Deploy keys > Add deploy key"
        echo "(bez zaznaczania 'Allow write access'). Wklej cala linie ponizej:"
        echo
        cat "$KEY.pub"
        echo
        read -r -p "Nacisnij Enter, gdy klucz bedzie dodany (Ctrl+C przerywa)... "
    done
    echo "Klucz dziala."
fi

echo "== 3/6 Kod =="
cd "$DIR"
[ -d app/.git ] || git clone "$REPO" app
git -C app config core.sshCommand "ssh -i $KEY -o IdentitiesOnly=yes"
if [ ! -L public_html ]; then
    [ -e public_html ] && mv public_html "public_html.bak.$(date +%s)"
    ln -s app/public public_html
fi
devil www options "$DOMAIN" php_openbasedir "$DIR/app/"

echo "== 4/6 Zaleznosci PHP =="
cd "$DIR/app"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction

echo "== 5/6 Plik .env =="
if [ ! -f .env ]; then
    echo "Podaj dane bazy MySQL utworzonej w panelu MyDevil."
    read -r -p "Host bazy (np. mysql59.mydevil.net): " DB_HOST
    read -r -p "Nazwa bazy: " DB_DATABASE
    read -r -p "Uzytkownik bazy: " DB_USERNAME
    read -r -s -p "Haslo bazy (nie bedzie widoczne): " DB_PASSWORD
    echo
    export DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD
    export APP_URL="https://$DOMAIN"
    cp .env.example .env
    chmod 600 .env
    cat > /tmp/tobym-env.$$.php <<'PHP'
<?php
$quote = fn (string $v): string => '"'.addcslashes($v, "\\\"$").'"';
$values = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_URL' => getenv('APP_URL'),
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => getenv('DB_HOST'),
    'DB_PORT' => '3306',
    'DB_DATABASE' => getenv('DB_DATABASE'),
    'DB_USERNAME' => getenv('DB_USERNAME'),
    'DB_PASSWORD' => $quote(getenv('DB_PASSWORD')),
];
$env = file_get_contents('.env');
foreach ($values as $key => $value) {
    $line = $key.'='.$value;
    $count = 0;
    $env = preg_replace_callback(
        '/^#?\s*'.preg_quote($key, '/').'=.*$/m',
        fn () => $line,
        $env,
        1,
        $count,
    );
    if ($count === 0) {
        $env .= PHP_EOL.$line.PHP_EOL;
    }
}
file_put_contents('.env', $env);
PHP
    php /tmp/tobym-env.$$.php
    rm -f /tmp/tobym-env.$$.php
    php artisan key:generate --force
else
    echo ".env juz istnieje, zostawiam bez zmian."
fi

echo "== 6/6 Baza i cache =="
php artisan migrate --force
php artisan optimize

echo
echo "Gotowe. Teraz u siebie uruchom: ship \"Pierwszy deploy\""
