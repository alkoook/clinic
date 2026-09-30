<?php

namespace App\Exceptions;

use RuntimeException;

final class TelegramException extends RuntimeException
{
    public static function missingConfiguration(): self
    {
        return new self('إعدادات تيلغرام غير مكتملة. تحقق من رمز البوت ومعرّف المحادثة.');
    }

    public static function deliveryFailed(): self
    {
        return new self('تعذر إرسال رسالة تيلغرام. تحقق من الإعدادات والاتصال ثم حاول مجددًا.');
    }
}
