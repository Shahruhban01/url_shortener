<!-- Rough Work, While coding the project -->
--- Making Migrations first
# php artisan migrate
# php artisan make:model Company -m
# php artisan make:migration add_company_and_role_to_users_table --table=users
# php artisan make:model ShortUrl -m

# php artisan migrate:status
# php artisan db:seed --class=SuperAdminSeeder

--- using breeze, blade for authentication
# composer require laravel/breeze --dev
## php artisan breeze:install blade

--- Middlewares
# php artisan make:middleware RoleMiddleware

# php artisan make:controller ShortUrlController

--- Routes
# php artisan route:list

--- Url Shortener generator
# $code = Str::random(8);

# php artisan make:controller InvitationController  


# Added public function storeCompanyMemberInvitation(Request $request) in invitation controller


# php artisan make:controller TeamController