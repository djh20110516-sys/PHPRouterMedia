<?php

define('ACCESS', TRUE);

/**
 * PHPMediaServer SSDP Daemon
 * 
 * UPnP SSDP discovery responder - makes the media server discoverable
 * on the local network by DLNA/UPnP clients (NPlayer, VLC, Infuse, etc.)
 * 
 * Usage: sudo -u root php /path/to/dlna/ssdpd.php
 * Requires root to bind UDP port 1900 (standard SSDP port)
 */

// --- Bootstrap ---
define('DS', DIRECTORY_SEPARATOR);
define('PPATH_BASE', dirname(dirname(__FILE__)));
define('PPATH_CORE', PPATH_BASE . DS . 'core');
define('PPATH_ACTIONS', PPATH_BASE . DS . 'actions');
define('PPATH_CACHE', PPATH_BASE . DS . 'cache');
define('PPATH_TEMP', PPATH_CACHE . DS . 'temp');
define('PPATH_LANG', PPATH_BASE . DS . 'lang');
define('PPATH_MEDIAINFO', PPATH_CACHE . DS . 'mediadata');
define('PPATH_IMGS', PPATH_BASE . DS . 'imgs');

// CLI-only
if (PHP_SAPI !== 'cli') {
    die("This script must be run from CLI.\n");
}

// Suppress errors for CLI (we handle errors manually)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load core
require(PPATH_CORE . DS . 'functions.php');
require(PPATH_BASE . DS . 'config.php');
require(PPATH_CORE . DS . 'functions.bd.php');
require(PPATH_CORE . DS . 'functions.dlna.php');
require(PPATH_CORE . DS . 'functions.media.php');

if (!defined('DLNA_ACTIVE') || !DLNA_ACTIVE) {
    die("DLNA_ACTIVE is FALSE in config.php. Enable it first.\n");
}

// --- Signal handling ---
$running = true;
declare(ticks = 1);

function shutdown_handler() {
    global $running;
    $running = false;
    echo date('Y-m-d H:i:s') . " SSDP daemon shutting down...\n";
}

pcntl_signal(SIGINT, 'shutdown_handler');
pcntl_signal(SIGTERM, 'shutdown_handler');

// --- SSDP Constants ---
define('SSDP_MULTICAST_IP', '239.255.255.250');
define('SSDP_PORT', 1900);
define('NOTIFY_INTERVAL', 60); // Send NOTIFY alive every 60 seconds
define('SOCKET_TIMEOUT', 5);    // socket_select timeout in seconds

// --- Get server info ---
$serverIp = DLNA_BINDIP;
$serverPort = 8100;
$serverBase = DLNA_WEB_BASESERVER_HTTP;
$locationUrl = rtrim($serverBase, '/') . '/dlna/rootDesc.php';

echo date('Y-m-d H:i:s') . " PHPMediaServer SSDP Daemon starting...\n";
echo "  Server: $serverBase\n";
echo "  Location: $locationUrl\n";
echo "  Binding to 0.0.0.0:" . SSDP_PORT . "\n";
echo "  NOTIFY interval: " . NOTIFY_INTERVAL . "s\n\n";

// --- Create UDP socket ---
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$socket) {
    die("socket_create() failed: " . socket_strerror(socket_last_error()) . "\n");
}

socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
if (defined('SO_REUSEPORT')) {
    @socket_set_option($socket, SOL_SOCKET, SO_REUSEPORT, 1);
}

// Bind to all interfaces on SSDP port (requires root)
if (!@socket_bind($socket, '0.0.0.0', SSDP_PORT)) {
    $err = socket_strerror(socket_last_error($socket));
    echo "ERROR: socket_bind(0.0.0.0, " . SSDP_PORT . ") failed: $err\n";
    echo "       Port 1900 requires root privileges.\n";
    echo "       Try: sudo php " . __FILE__ . "\n";
    socket_close($socket);
    exit(1);
}

// Set non-blocking for reading
socket_set_nonblock($socket);

// Join SSDP multicast group to receive M-SEARCH requests
$mreq = array(
    'group' => SSDP_MULTICAST_IP,
    'interface' => DLNA_BINDIP
);
if (!@socket_set_option($socket, IPPROTO_IP, MCAST_JOIN_GROUP, $mreq)) {
    // Fallback for older PHP: use IP_ADD_MEMBERSHIP with packed struct
    $mreq_packed = pack('Nn', ip2long(SSDP_MULTICAST_IP), 0);
    @socket_set_option($socket, IPPROTO_IP, 12, $mreq_packed); // 12 = IP_ADD_MEMBERSHIP
}
echo "  Joined multicast group " . SSDP_MULTICAST_IP . ":" . SSDP_PORT . " on " . DLNA_BINDIP . "\n\n";

// --- Helper: send SSDP response ---
function ssdp_respond($socket, $data, $destIp, $destPort) {
    $uuidStr = dlna_get_uuidStr();
    
    // Check if it's an M-SEARCH request
    if (preg_match('/^M-SEARCH\s+\*\s+HTTP/i', $data)) {
        $st = '';
        $mx = 3;
        $man = '';
        
        foreach (explode("\r\n", $data) as $line) {
            if (preg_match('/^ST:\s*(.+)$/i', $line, $m)) $st = trim($m[1]);
            if (preg_match('/^MX:\s*(\d+)$/i', $line, $m)) $mx = (int)$m[1];
            if (preg_match('/^MAN:\s*(.+)$/i', $line, $m)) $man = trim($m[1]);
        }
        
        $locationUrl = $GLOBALS['locationUrl'];
        
        // Build response based on ST header
        $responses = array();
        
        if ($st == 'ssdp:all' || $st == 'upnp:rootdevice') {
            $responses[] = build_msearch_response($locationUrl, $uuidStr, 'upnp:rootdevice', 'uuid:' . $uuidStr . '::upnp:rootdevice');
        }
        if ($st == 'ssdp:all' || $st == 'uuid:' . $uuidStr) {
            $responses[] = build_msearch_response($locationUrl, $uuidStr, 'uuid:' . $uuidStr, 'uuid:' . $uuidStr);
        }
        if ($st == 'ssdp:all' || $st == 'urn:schemas-upnp-org:device:MediaServer:1') {
            $responses[] = build_msearch_response($locationUrl, $uuidStr, 'urn:schemas-upnp-org:device:MediaServer:1', 'uuid:' . $uuidStr . '::urn:schemas-upnp-org:device:MediaServer:1');
        }
        if ($st == 'ssdp:all' || $st == 'urn:schemas-upnp-org:service:ContentDirectory:1') {
            $responses[] = build_msearch_response($locationUrl, $uuidStr, 'urn:schemas-upnp-org:service:ContentDirectory:1', 'uuid:' . $uuidStr . '::urn:schemas-upnp-org:service:ContentDirectory:1');
        }
        if ($st == 'ssdp:all' || $st == 'urn:schemas-upnp-org:service:ConnectionManager:1') {
            $responses[] = build_msearch_response($locationUrl, $uuidStr, 'urn:schemas-upnp-org:service:ConnectionManager:1', 'uuid:' . $uuidStr . '::urn:schemas-upnp-org:service:ConnectionManager:1');
        }
        
        foreach ($responses as $resp) {
            @socket_sendto($socket, $resp, strlen($resp), 0, $destIp, $destPort);
        }
        
        echo date('Y-m-d H:i:s') . " M-SEARCH responded from $destIp:$destPort (ST: $st)\n";
    }
}

function build_msearch_response($location, $uuid, $st, $usn) {
    $resp = "HTTP/1.1 200 OK\r\n";
    $resp .= "CACHE-CONTROL: max-age=600\r\n";
    $resp .= "DATE: " . gmdate('D, d M Y H:i:s \\G\\M\\T') . "\r\n";
    $resp .= "ST: $st\r\n";
    $resp .= "USN: $usn\r\n";
    $resp .= "EXT:\r\n";
    $resp .= "SERVER: Linux UPnP/1.0 DLNADOC/1.50 PHPMediaServer/0\r\n";
    $resp .= "X-User-Agent: redsonic\r\n";
    $resp .= "LOCATION: $location\r\n";
    $resp .= "Content-Length: 0\r\n";
    $resp .= "\r\n";
    return $resp;
}

// --- Helper: send NOTIFY alive ---
function ssdp_notify($socket) {
    global $locationUrl;
    $uuidStr = dlna_get_uuidStr();
    
    // Send NOTIFY for root device, UUID, device type, and services
    $notifies = array(
        array('upnp:rootdevice', 'uuid:' . $uuidStr . '::upnp:rootdevice'),
        array('uuid:' . $uuidStr, 'uuid:' . $uuidStr),
        array('urn:schemas-upnp-org:device:MediaServer:1', 'uuid:' . $uuidStr . '::urn:schemas-upnp-org:device:MediaServer:1'),
        array('urn:schemas-upnp-org:service:ContentDirectory:1', 'uuid:' . $uuidStr . '::urn:schemas-upnp-org:service:ContentDirectory:1'),
        array('urn:schemas-upnp-org:service:ConnectionManager:1', 'uuid:' . $uuidStr . '::urn:schemas-upnp-org:service:ConnectionManager:1'),
    );
    
    foreach ($notifies as $n) {
        $nt = $n[0];
        $usn = $n[1];
        
        $msg = "NOTIFY * HTTP/1.1\r\n";
        $msg .= "HOST: " . SSDP_MULTICAST_IP . ":" . SSDP_PORT . "\r\n";
        $msg .= "CACHE-CONTROL: max-age=600\r\n";
        $msg .= "LOCATION: $locationUrl\r\n";
        $msg .= "NT: $nt\r\n";
        $msg .= "NTS: ssdp:alive\r\n";
        $msg .= "SERVER: Linux UPnP/1.0 DLNADOC/1.50 PHPMediaServer/0\r\n";
        $msg .= "X-User-Agent: redsonic\r\n";
        $msg .= "USN: $usn\r\n";
        $msg .= "Content-Length: 0\r\n";
        $msg .= "\r\n";
        
        @socket_sendto($socket, $msg, strlen($msg), 0, SSDP_MULTICAST_IP, SSDP_PORT);
    }
    
    echo date('Y-m-d H:i:s') . " NOTIFY ssdp:alive sent\n";
}

// --- Main loop ---
$lastNotify = 0;

// Send initial NOTIFY announcements
ssdp_notify($socket);
$lastNotify = time();

while ($running) {
    $read = array($socket);
    $write = NULL;
    $except = NULL;
    $tv_sec = SOCKET_TIMEOUT;
    $tv_usec = 0;
    
    $result = @socket_select($read, $write, $except, $tv_sec, $tv_usec);
    
    if (!$running) break;
    
    if ($result === false) {
        $err = socket_last_error($socket);
        if ($err != SOCKET_EINTR) {
            echo date('Y-m-d H:i:s') . " socket_select error: " . socket_strerror($err) . "\n";
        }
        socket_clear_error($socket);
        continue;
    }
    
    // Read available data
    if ($result > 0 && in_array($socket, $read)) {
        $data = '';
        $fromIp = '';
        $fromPort = 0;
        
        if (@socket_recvfrom($socket, $data, 8192, 0, $fromIp, $fromPort)) {
            if (strlen($data) > 0) {
                ssdp_respond($socket, $data, $fromIp, $fromPort);
            }
        }
    }
    
    // Periodic NOTIFY announcements
    $now = time();
    if ($now - $lastNotify >= NOTIFY_INTERVAL) {
        ssdp_notify($socket);
        $lastNotify = $now;
    }
}

// --- Cleanup ---
@socket_shutdown($socket, 0);
socket_close($socket);
echo date('Y-m-d H:i:s') . " SSDP daemon stopped.\n";
