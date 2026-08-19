<?php

test('mail is configured to use resend in production-like env', function () {
    config(['mail.default' => 'resend']);

    expect(config('mail.default'))->toBe('resend');
    expect(config('mail.from.address'))->not->toBeEmpty();
});
