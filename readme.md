# AB InBev technical test

This test was made for a drupal developer position at abinbev.

The file you are looking for is [here](https://github.com/JorgeRinaldi1995/abinbev-drupal-test/tree/main/web/modules/custom/voting_system) 

## Steps to setup the environment

On main folder run the following comands:

```
lando start
```

```
lando composer install
```

```
lando drush site:install --db-url=mysql://drupal10:drupal10@database/drupal10 -y
```

```
lando drush cr
```

```
lando db-import dump.sql.gz
```

User for test:

login: auth_user
password: 12345