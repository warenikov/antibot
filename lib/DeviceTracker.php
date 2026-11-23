<?php

declare(strict_types=1);

namespace Alexw\Antibot;

use Bitrix\Main\Type\DateTime;

class DeviceTracker
{
    private const UPLOAD_DIR = '/upload/alexw.antibot/fp';

    /**
     * Регистрация/обновление устройства по хиту: привязка к аккаунту, счётчик, железо, картинка.
     *
     * @return array{ok: bool, needImage?: bool}
     */
    public static function track(string $fp, array $device, string $imageDataUrl): array
    {
        if ($fp === '' || !preg_match('/^[a-f0-9]{64}$/', $fp)) {
            return ['ok' => false];
        }

        global $USER;
        $userId = is_object($USER) ? (int)$USER->GetID() : 0;
        $now = new DateTime();

        $row = DeviceTable::getList([
            'filter' => ['=FP' => $fp],
            'limit'  => 1,
        ])->fetch();

        $imagePath = $row['IMAGE_PATH'] ?? null;

        if ($row) {
            $update = [
                'LAST_SEEN' => $now,
                'HITS'      => (int)$row['HITS'] + 1,
            ];
            if ($userId > 0 && (int)$row['USER_ID'] !== $userId) {
                $update['USER_ID'] = $userId;
            }
            if ($device !== []) {
                $update['DEVICE_INFO'] = json_encode($device, JSON_UNESCAPED_UNICODE);
            }
            DeviceTable::update($row['ID'], $update);
            $deviceId = (int)$row['ID'];
        } else {
            $add = [
                'FP'         => $fp,
                'USER_ID'    => $userId,
                'FIRST_SEEN' => $now,
                'LAST_SEEN'  => $now,
                'HITS'       => 1,
            ];
            if ($device !== []) {
                $add['DEVICE_INFO'] = json_encode($device, JSON_UNESCAPED_UNICODE);
            }
            $result = DeviceTable::add($add);
            if (!$result->isSuccess()) {
                return ['ok' => false];
            }
            $deviceId = (int)$result->getId();
        }

        // Картинка canvas: сохраняем один раз на устройство, дедуп по имени хеша
        if (\COption::GetOptionString(Settings::MODULE_ID, 'save_fp_image', 'N') === 'Y' && !$imagePath) {
            if ($imageDataUrl !== '') {
                $imagePath = self::saveImage($fp, $imageDataUrl);
                if ($imagePath !== null) {
                    DeviceTable::update($deviceId, ['IMAGE_PATH' => $imagePath]);
                }
            } else {
                return ['ok' => true, 'needImage' => true];
            }
        }

        return ['ok' => true];
    }

    private static function saveImage(string $fp, string $dataUrl): ?string
    {
        if (!str_starts_with($dataUrl, 'data:image/png;base64,')) {
            return null;
        }

        $binary = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
        if ($binary === false || strlen($binary) > 2 * 1024 * 1024) {
            return null;
        }

        $absDir = $_SERVER['DOCUMENT_ROOT'] . self::UPLOAD_DIR;
        if (!is_dir($absDir)) {
            mkdir($absDir, 0755, true);
        }

        $relPath = self::UPLOAD_DIR . '/' . $fp . '.png';
        $absPath = $_SERVER['DOCUMENT_ROOT'] . $relPath;

        if (!file_exists($absPath)) {
            if (file_put_contents($absPath, $binary) === false) {
                return null;
            }
        }

        return $relPath;
    }
}
