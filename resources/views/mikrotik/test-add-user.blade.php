@php
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

// Create config object with parameters
$config =
    (new Config())
        ->set('host', '127.0.0.1')
        ->set('port', 8728)
        ->set('pass', 'admin')
        ->set('user', 'admin');

// Initiate client with config object
$client = new Client($config);

// Build query for details about user profile
$query = new Query('/ip/hotspot/user/profile/print');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Try Mikrotik</title>
</head>
<body>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>Name</th>
            <td>{{ $out[0]["name"] ?? '' }}</td>
        </tr>
        <tr>
            <th>RX Packets/s</th>
            <td>{{ $out[0]['rx-packets-per-second'] ?? '' }}</td>
        </tr>
        <tr>
            <th>RX Bits/s</th>
            <td>{{ $out[0]['rx-bits-per-second'] ?? '' }}</td>
        </tr>
        <tr>
            <th>FP RX Packets/s</th>
            <td>{{ $out[0]['fp-rx-packets-per-second'] ?? '' }}</td>
        </tr>
        <tr>
            <th>FP RX Bits/s</th>
            <td>{{ $out[0]['fp-rx-bits-per-second'] ?? '' }}</td>
        </tr>
        <tr>
            <th>RX Drops/s</th>
            <td>{{ $out[0]['rx-drops-per-second'] ?? '' }}</td>
        </tr>
        <tr>
            <th>RX Errors/s</th>
            <td>{{ $out[0]['rx-errors-per-second'] ?? '' }}</td>
        </tr>
        <tr>
            <th>TX Packets/s</th>
            <td>{{ $out[0]['tx-packets-per-second'] ?? '' }}</td>
        </tr>
        <tr>
            <th>TX Bits/s</th>
            <td>{{ $out[0]['tx-bits-per-second'] ?? '' }}</td>
        </tr>
        <tr>
            <th>FP TX Packets/s</th>
            <td>{{ $out[0]['fp-tx-packets-per-second'] ?? '' }}</td>
        </tr>
        <tr>
            <th>FP TX Bits/s</th>
            <td>{{ $out[0]['fp-tx-bits-per-second'] ?? '' }}</td>
        </tr>
        <tr>
            <th>TX Drops/s</th>
            <td>{{ $out[0]['tx-drops-per-second'] ?? '' }}</td>
        </tr>
        <tr>
            <th>TX Queue Drops/s</th>
            <td>{{ $out[0]['tx-queue-drops-per-second'] ?? '' }}</td>
        </tr>
        <tr>
            <th>TX Errors/s</th>
            <td>{{ $out[0]['tx-errors-per-second'] ?? '' }}</td>
        </tr>
    </table>
    
</body>
</html>