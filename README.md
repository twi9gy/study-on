# study-on
Инструкция по сборке:

1. Создайте папку под проект: mkdir project
2. Перейдите в папку проекта: cd project
3. Скопируйте проект с github: git clone https://github.com/twi9gy/study-on
4. Установите необходимые пакеты: composer install
5. Запустите контейнеры приложения: make up
6. Настройте файл .env для подключения к бд.
7. Создайте базу данных: docker-compose exec php bin/console doctrine:database:create
8. Примените миграцию: make migrate
9. Загрузите fixtures: make fixtload
