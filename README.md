Simfra Translations Module
A Laravel package for managing translations with a Vue.js frontend.
Installation

Add the package to your project:
composer require simfra/translations-module


Publish configuration and migrations:
php artisan vendor:publish --tag=translations-config
php artisan vendor:publish --tag=translations-migrations


Run migrations:
php artisan migrate


Ensure App\Models\Language exists:

The package assumes a languages table with columns iso_code (string, 2 chars) and name (string).
Example schema:Schema::create('languages', function (Blueprint $table) {
$table->id();
$table->string('iso_code', 2)->unique();
$table->string('name');
$table->timestamps();
});




Add sample data (optional):
INSERT INTO languages (iso_code, name) VALUES ('pl', 'Polski'), ('en', 'English');
INSERT INTO translations (lang, key, value, readonly) VALUES
('pl', 'auth.login', 'Zaloguj', false),
('pl', 'auth.logout', '', false),
('pl', 'welcome', 'Witaj', false);


Ensure dependencies:

Install inertiajs/inertia-laravel and notivue/vue3:composer require inertiajs/inertia-laravel notivue/vue3


Ensure AppLayout.vue and UniversalModal.vue are available in your project at @/Layouts/AppLayout.vue and @/Components/UniversalModal.vue.


Access the module:Visit /translations in your browser.


Configuration

Edit config/translations.php to change the route prefix (default: translations).

Usage

Manage translations: Add, edit, delete, or import translations via the UI.
Search: Search by key or value.
Groups: Filter by translation groups or show missing translations.
Routes:
GET /translations - Display the translation management interface.
GET /translations/get - Fetch translations.
POST /translations/store - Save a single translation.
POST /translations/bulk-store - Save multiple translations.
POST /translations/import - Import translations from a JSON file.
DELETE /translations/{id} - Delete a translation by ID.



Requirements

PHP ^8.1
Laravel ^10.0 or ^11.0
Inertia.js (Vue 3 adapter)
Notivue for notifications
App\Models\Language model with a languages table

License
MIT
