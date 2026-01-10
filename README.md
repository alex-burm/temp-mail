# Тестирование SMTP-сервиса (нагрузка и утечки памяти)

`smtp-source` — утилита для генерации SMTP-нагрузки.


если подобной утилиты нет в ваших пакетных менеджерах, опционально можно поставить в докере
```bash
docker run --rm -it debian:12 bash
apt update
apt install -y postfix
```

### Варианты тестирования

Берем за основу `test@localhost`, должен быть разрешен в списке доступных адресов

Параметры, которые тебе чаще всего нужны для нагрузки:
-s sess — число сессии (SMTP соединении)
-m msgs — всего писем
-l msglen — длина сообщения (байты)
-c — коннекты без DATA (нагрузка на handshake)
-d — включить DATA (письма реально отправляются)
-r rcptcount — число RCPT TO на письмо
-R delay — задержка между получателями (сек)
-w delay — задержка между сообщениями (сек)

Простой тест на 10 сессий
```bash
smtp-source -d -f test@localhost -t test@localhost -s 10 -l 1 -m 10 host.docker.internal:25
```

Тест на 10 000 сессий
```bash
smtp-source -f test@localhost -t test@localhost -s 1 -m 10000 -l 100 host.docker.internal:25
```

Много коротких сессий, маленькие письма (поиск утечек коннект/дисконнект)
```bash
smtp-source -d -f test@localhost -t test@localhost -s 5000 -m 5000 -l 200 host.docker.internal:25
```

Меньше сессии, но большие письма (поиск утечек на DATA/буферах):
```bash
smtp-source -d -s 200 -m 200 -l 5000000 host.docker.internal:25
```

Тестирование без RCPT
```bash
smtp-source -c -s 1000 -m 0 host.docker.internalnal:25
```

### Мониторинг процесса:
RSS растет и не падает после окончания → утечка
```bash
watch 'ps -o pid,rss,vsz,command -p <pid>'
```

FD растут → утечка сокетов
```bash
watch 'lsof -p <pid> | wc -l'
```

ESTABLISHED копятся → не закрываешь соединения
```bash
watch 'lsof -i TCP:25 -sTCP:ESTABLISHED | wc -l'
```

### Запуск с профилированием

php \
-d xdebug.mode=profile \
-d xdebug.start_with_request=yes \
-d xdebug.output_dir=var/xdebug \
-d xdebug.profiler_output_name=cachegrind.out.%p \
-d opcache.enable_cli=0 \
-d opcache.jit=off \
bin/console smtp:serve -vvv
