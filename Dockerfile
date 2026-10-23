# vnStat PHP frontend — production & test container
FROM php:latest

WORKDIR /app

# Copy application
COPY . .

# Create non-root user and switch
RUN useradd -m -u 1000 -s /bin/bash vnstat && chown -R vnstat:vnstat /app
USER vnstat

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app"]