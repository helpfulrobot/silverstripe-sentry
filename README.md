# Sentry.io integration for SilverStripe

This is a simple module that binds Sentry.io to the error & exception handler of SilverStripe.

It also allows for sending error and exception data to local Sentry installations.

## Requirements

Besides having a Sentry instance to connect-to, SilverStripe v3.1.0+ should work fine. (Not tested with SilverStripe 4...yet)

## Setup

Add the Composer package as a dependency to your project:

	composer require silverstripe/sentry: 1.0

Invoke the factory method:

    SS_Log::add_writer(SentryLogWriter::factory(), SS_Log::ERR, '<=');

