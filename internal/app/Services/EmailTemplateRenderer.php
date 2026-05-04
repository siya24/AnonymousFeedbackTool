<?php declare(strict_types=1);

namespace App\Services;

final class EmailTemplateRenderer
{
    public function __construct(
        private readonly string $templateBasePath,
    ) {}

    
    public function renderNotification(array $data): string
    {
        $templatePath = rtrim($this->templateBasePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'notification.php';
        if (!file_exists($templatePath)) {
            throw new \RuntimeException('Email template not found: ' . $templatePath);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $templatePath;
        return (string) ob_get_clean();
    }
}
