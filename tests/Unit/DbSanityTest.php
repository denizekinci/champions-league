<?php

test('db sanity check', function () {
    dump('env: '.app()->environment());
    dump('default connection: '.config('database.default'));
    dump('pgsql database: '.config('database.connections.pgsql.database'));

    expect(app()->environment('testing'))->toBeTrue()
        ->and(config('database.connections.pgsql.database'))->toBe('testing');
});
