## Installation 

This guide assumes you have Laravel installed on your machine, Otherwise refer to Laravel official documentation for [Installation guide](https://laravel.com/docs/8.x/installation).

## Laravel App Setup

```
# clone the repo
$ git clone https://github.com/RemontadaCrypto/APIV1.git my-folder

# go into app's directory
$ cd my-folder

# install app's dependencies
$ composer install

```

Copy content of ".env.example" to ".env". Then in file ".env" replace the following configuration:

- DB_DATABASE=laravel
- DB_USERNAME=root
- DB_PASSWORD=

- PUSHER_APP_ID=
- PUSHER_APP_KEY=
- PUSHER_APP_SECRET=

- CRYPTO_API_KEY=
- MAIL_USERNAME=
- MAIL_PASSWORD=
- MAIL_FROM_ADDRESS=

To this:

- DB_DATABASE=database_name
- DB_USERNAME=database_username
- DB_PASSWORD=database_password

- PUSHER_APP_ID=pusher_app_id
- PUSHER_APP_KEY=pusher_app_key
- PUSHER_APP_SECRET=pusher_app_secret

- CRYPTO_API_KEY=crypto_api_key

- MAIL_USERNAME=mailtrap_username
- MAIL_PASSWORD=mailtrap_passord
- MAIL_FROM_ADDRESS=mail_from_address

```
# generate app key
$ php artisan key:generate

# generate jwt secret
$ php artisan jwt:secret

# run database migration
$ php artisan migrate

```

## Usage

```
# start local server
$ php artisan serve

# start queue workers
$ php artisan queue:work

```
