# MyShop

MyShop is a PHP web application for managing a shop and its customers. Customers can find shops, browse products, make purchases, view their purchase history, and check loyalty points. Managers can manage suppliers, shops, products, and customers.

The application uses PostgreSQL for its data and Bootstrap 5 for its interface. The database schema is in the `myshop` schema.

## Features

- Customer and manager sign-in, with role-specific pages.
- Customer shop search, product purchasing, purchase history, and loyalty-point display.
- Manager tools for suppliers, shops, products, and customers.
- Password changes and session logout.

## Project layout

| Path | Purpose |
| --- | --- |
| `index.php` | Entry point; routes signed-in users to the application. |
| `auth/logIn.php` | Sign-in page and authentication. |
| `homeCustomer.php` | Customer dashboard and shop search. |
| `buy.php` | Purchase flow. |
| `homeManager.php` | Manager dashboard and management sections. |
| `manageProduct.php`, `manageShop.php`, `manageSupplier.php` | Manager workflows for products, shops, and suppliers. |
| `lib/` | Shared page components and customer, product, purchase, shop, and supplier functionality. |
| `conf/conf.php` | PostgreSQL connection configuration. |
| `Documentation.pdf` | Database documentation. |

## Database documentation

For the database structure—including tables and relationships—and the database triggers and functions, see [Documentation.pdf](Documentation.pdf). The application sets its PostgreSQL search path to `myshop`. Database setup details are maintained in the PDF rather than as SQL migration files in this repository.

## Configuration and running

You will need PHP with the PostgreSQL extension enabled, a PostgreSQL database set up according to [Documentation.pdf](Documentation.pdf), and a web server capable of running PHP.

The connection configuration in `conf/conf.php` expects `MYHOST`, `MYUSER`, `MYPSW`, and `MYDB` to be available through PHP's `env()` helper. `.env.example` lists these settings, but the repository does not include a `.env` loader; make sure your PHP runtime or deployment bootstrap provides the helper and values. Do not commit credentials.

After configuring PHP and the database, serve the repository with your PHP-capable web server and open `index.php` in a browser.
