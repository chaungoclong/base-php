<?php

declare(strict_types=1);


use BasePHP\Database\Connection;
use BasePHP\Database\Connector;

it('tests nested transactions', function () {
    $connector = new Connector();

    $pdo = $connector->getConnection([
        'host' => 'localhost',
        'port' => 3306,
        'username' => 'long',
        'password' => 'tnt',
        'database' => 'test'
    ]);

    $connection = new Connection($pdo);

    $connection->execute('CREATE TABLE IF NOT EXISTS customers (id INTEGER PRIMARY KEY AUTO_INCREMENT, name TEXT)');
    $connection->execute('TRUNCATE TABLE customers');

    $connection->beginTransaction();
    $connection->execute('INSERT INTO customers (name) VALUES ("Iron Man")');
    // Nested transaction
    $connection->beginTransaction();
    $connection->execute('INSERT INTO customers (name) VALUES ("Superman")');
    $connection->commit();

    $result = $connection->select('customers', ['name']);
    expect($result)->toBeArray();
    expect(count($result))->toBe(2);
    $connection->rollback();

    $resultAfterRollback = $connection->select('customers', ['name']);
    expect($resultAfterRollback)->toBeArray();
    expect(count($resultAfterRollback))->toBe(0);
});
