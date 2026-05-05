web: mkdir -p storage/app/public/payment-proofs storage/app/public/official-receipts storage/app/public/applicant-photos storage/app/public/reassessment-payments storage/app/public/twsp_documents && php artisan migrate --force && php artisan route:clear && php artisan serve --host=0.0.0.0 --port=$PORT
worker: php artisan queue:work --tries=3 --timeout=90 --sleep=3
