# MaeumOn Backend

Laravel 12 기반의 RESTful API 백엔드 프로젝트입니다.

## 프로젝트 구조

```
MaeumOn_Back/
├── app/
│   ├── Console/            # Artisan 명령어
│   ├── Exceptions/         # 예외 처리
│   ├── Http/
│   │   ├── Controllers/    # 컨트롤러
│   │   ├── Middleware/     # 미들웨어
│   │   └── Requests/       # Form Request 검증
│   ├── Models/             # Eloquent 모델
│   ├── Providers/          # 서비스 프로바이더
│   └── Services/           # 비즈니스 로직 (직접 생성)
│
├── bootstrap/              # 앱 부트스트랩
├── config/                 # 설정 파일
├── database/
│   ├── factories/          # 모델 팩토리
│   ├── migrations/         # 마이그레이션
│   └── seeders/            # 시더
│
├── public/                 # 웹 서버 루트
├── resources/
│   └── views/              # 블레이드 뷰 (필요시)
│
├── routes/
│   ├── api.php             # API 라우트
│   ├── web.php             # 웹 라우트
│   └── console.php         # 콘솔 라우트
│
├── storage/                # 파일 저장소
├── tests/                  # 테스트
├── vendor/                 # Composer 패키지
│
├── .env                    # 환경 변수
├── .env.example            # 환경 변수 예시
├── artisan                 # Artisan CLI
├── composer.json           # Composer 설정
└── phpunit.xml             # PHPUnit 설정
```

## 기술 스택

| 기술 | 버전 | 설명 |
|------|------|------|
| PHP | 8.2+ | 서버 언어 |
| Laravel | 12.x | 웹 프레임워크 |
| MySQL/MariaDB | 8.0+ / 10.x+ | 데이터베이스 |
| Redis | 7.x | 캐시/세션/큐 (선택) |
| Composer | 2.x | 패키지 관리자 |

## 요구 사항

- **PHP**: 8.2 이상
- **Composer**: 2.x
- **MySQL**: 8.0+ 또는 MariaDB 10.x+
- **확장 모듈**: BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML

## 로컬 개발 환경 설정

### 1. 의존성 설치

```bash
cd MaeumOn_Back
composer install
```

### 2. 환경 변수 설정

```bash
# .env.example을 복사하여 .env 생성
cp .env.example .env

# 애플리케이션 키 생성
php artisan key:generate
```

### 3. .env 파일 설정

```env
APP_NAME=MaeumOn
APP_ENV=local
APP_KEY=base64:xxxxxxxxxxxxx
APP_DEBUG=true
APP_URL=http://localhost:8000

# 데이터베이스 설정
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=maeumon
DB_USERNAME=root
DB_PASSWORD=your_password

# 캐시 설정 (선택)
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# Redis 사용 시 (선택)
# CACHE_DRIVER=redis
# SESSION_DRIVER=redis
# QUEUE_CONNECTION=redis
# REDIS_HOST=127.0.0.1
# REDIS_PASSWORD=null
# REDIS_PORT=6379

# 메일 설정 (선택)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@maeumon.com"
MAIL_FROM_NAME="${APP_NAME}"

# JWT 설정 (API 인증 사용 시)
# JWT_SECRET=your_jwt_secret
# JWT_TTL=60
```

### 4. 데이터베이스 설정

```bash
# MySQL에서 데이터베이스 생성
mysql -u root -p
CREATE DATABASE maeumon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;

# 마이그레이션 실행
php artisan migrate

# 시더 실행 (선택)
php artisan db:seed
```

### 5. 개발 서버 실행

```bash
php artisan serve
```

서버 접속: http://localhost:8000

### 6. 스토리지 링크 생성 (파일 업로드 사용 시)

```bash
php artisan storage:link
```

## API 개발

### 라우트 정의

`routes/api.php`:
```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

// 인증 라우트
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// 인증 필요 라우트
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::put('/user', [UserController::class, 'update']);
});
```

### 컨트롤러 생성

```bash
# 컨트롤러 생성
php artisan make:controller AuthController

# 리소스 컨트롤러 생성
php artisan make:controller UserController --resource --api
```

### 모델 생성

```bash
# 모델 + 마이그레이션 + 팩토리 + 시더 생성
php artisan make:model Post -mfs

# 모델만 생성
php artisan make:model Comment
```

### Form Request 생성

```bash
php artisan make:request LoginRequest
```

## 유용한 Artisan 명령어

```bash
# 라우트 목록 확인
php artisan route:list

# 캐시 초기화
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 전체 캐시 초기화
php artisan optimize:clear

# 마이그레이션
php artisan migrate              # 실행
php artisan migrate:rollback     # 롤백
php artisan migrate:fresh        # 초기화 후 재실행
php artisan migrate:fresh --seed # 초기화 후 시더 실행

# 큐 워커 실행
php artisan queue:work

# 스케줄러 실행 (cron용)
php artisan schedule:run
```

## 테스트

```bash
# 전체 테스트 실행
php artisan test

# 특정 테스트 실행
php artisan test --filter=UserTest

# 커버리지 리포트
php artisan test --coverage
```

## 서버 배포

### 서버 요구 사항

- PHP 8.2+
- Composer
- MySQL 8.0+ / MariaDB 10.x+
- Nginx 또는 Apache
- SSL 인증서 (HTTPS)

### 1. 서버 초기 설정

```bash
# 프로젝트 클론
git clone https://github.com/your-repo/MaeumOn_Back.git /var/www/maeumon-api
cd /var/www/maeumon-api

# 의존성 설치 (개발 의존성 제외)
composer install --no-dev --optimize-autoloader

# 환경 변수 설정
cp .env.example .env
nano .env  # 프로덕션 설정으로 수정
```

### 2. 프로덕션 .env 설정

```env
APP_NAME=MaeumOn
APP_ENV=production
APP_KEY=base64:xxxxxxxxxxxxx
APP_DEBUG=false
APP_URL=https://api.maeumon.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=maeumon_prod
DB_USERNAME=maeumon_user
DB_PASSWORD=secure_password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 3. 프로덕션 최적화

```bash
# 앱 키 생성
php artisan key:generate

# 설정 캐시
php artisan config:cache

# 라우트 캐시
php artisan route:cache

# 뷰 캐시
php artisan view:cache

# 오토로더 최적화
composer dump-autoload --optimize

# 마이그레이션 실행
php artisan migrate --force

# 스토리지 링크
php artisan storage:link
```

### 4. 디렉토리 권한 설정

```bash
# 소유권 설정
sudo chown -R www-data:www-data /var/www/maeumon-api

# 권한 설정
sudo chmod -R 755 /var/www/maeumon-api
sudo chmod -R 775 /var/www/maeumon-api/storage
sudo chmod -R 775 /var/www/maeumon-api/bootstrap/cache
```

### 5. Nginx 설정

```nginx
server {
    listen 80;
    server_name api.maeumon.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.maeumon.com;

    root /var/www/maeumon-api/public;
    index index.php;

    # SSL 설정
    ssl_certificate /etc/letsencrypt/live/api.maeumon.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.maeumon.com/privkey.pem;

    # 보안 헤더
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    # 로그
    access_log /var/log/nginx/maeumon-api-access.log;
    error_log /var/log/nginx/maeumon-api-error.log;

    # CORS 설정 (필요시)
    add_header 'Access-Control-Allow-Origin' '*';
    add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS';
    add_header 'Access-Control-Allow-Headers' 'Authorization, Content-Type, X-Requested-With';

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 6. PHP-FPM 설정

`/etc/php/8.2/fpm/pool.d/www.conf`:
```ini
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
```

### 7. Supervisor 설정 (큐 워커)

`/etc/supervisor/conf.d/maeumon-worker.conf`:
```ini
[program:maeumon-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/maeumon-api/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/maeumon-api/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Supervisor 재시작
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start maeumon-worker:*
```

### 8. Cron 설정 (스케줄러)

```bash
# crontab 편집
sudo crontab -e

# 다음 줄 추가
* * * * * cd /var/www/maeumon-api && php artisan schedule:run >> /dev/null 2>&1
```

### 배포 스크립트 예시

```bash
#!/bin/bash
# deploy.sh

set -e

echo "Deploying MaeumOn Backend..."

cd /var/www/maeumon-api

# 유지보수 모드 활성화
php artisan down

# 최신 코드 가져오기
git pull origin main

# 의존성 설치
composer install --no-dev --optimize-autoloader

# 마이그레이션 실행
php artisan migrate --force

# 캐시 초기화 및 재생성
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 큐 워커 재시작
sudo supervisorctl restart maeumon-worker:*

# 유지보수 모드 해제
php artisan up

echo "Deployment complete!"
```

### Docker 배포

```dockerfile
# Dockerfile
FROM php:8.2-fpm-alpine

# 시스템 의존성 설치
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip

# PHP 확장 설치
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Composer 설치
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www

EXPOSE 9000

CMD ["php-fpm"]
```

```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build: .
    container_name: maeumon-api
    restart: unless-stopped
    volumes:
      - .:/var/www
    networks:
      - maeumon

  nginx:
    image: nginx:alpine
    container_name: maeumon-nginx
    restart: unless-stopped
    ports:
      - "8000:80"
    volumes:
      - .:/var/www
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf
    networks:
      - maeumon
    depends_on:
      - app

  db:
    image: mysql:8.0
    container_name: maeumon-db
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: maeumon
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_USER: maeumon_user
      MYSQL_PASSWORD: user_password
    volumes:
      - dbdata:/var/lib/mysql
    networks:
      - maeumon

  redis:
    image: redis:alpine
    container_name: maeumon-redis
    restart: unless-stopped
    networks:
      - maeumon

networks:
  maeumon:
    driver: bridge

volumes:
  dbdata:
    driver: local
```

## API 응답 형식

### 성공 응답

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "홍길동",
    "email": "hong@example.com"
  },
  "message": "요청이 성공적으로 처리되었습니다."
}
```

### 에러 응답

```json
{
  "success": false,
  "message": "인증에 실패했습니다.",
  "errors": {
    "email": ["이메일 형식이 올바르지 않습니다."]
  }
}
```

### 페이지네이션 응답

```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
```

## 트러블슈팅

### 500 에러 발생 시

```bash
# 로그 확인
tail -f storage/logs/laravel.log

# 권한 확인
sudo chmod -R 775 storage bootstrap/cache

# 캐시 초기화
php artisan optimize:clear
```

### 데이터베이스 연결 오류

```bash
# .env 설정 확인
cat .env | grep DB_

# MySQL 연결 테스트
mysql -h 127.0.0.1 -u root -p

# 설정 캐시 초기화
php artisan config:clear
```

### CORS 에러

`config/cors.php` 확인:
```php
return [
    'paths' => ['api/*'],
    'allowed_origins' => ['*'],  // 프로덕션에서는 특정 도메인으로 제한
    'allowed_methods' => ['*'],
    'allowed_headers' => ['*'],
];
```
