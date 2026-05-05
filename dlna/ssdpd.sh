#!/bin/bash
# PHPMediaServer SSDP Daemon Control Script
# Usage: ./dlna/ssdpd.sh {start|stop|restart|status}

DAEMON_PHP="/home/hao/download/phpmediaserver/dlna/ssdpd.php"
PID_FILE="/tmp/phpmediaserver-ssdpd.pid"
LOG_FILE="/tmp/phpmediaserver-ssdpd.log"

case "$1" in
    start)
        if [ -f "$PID_FILE" ] && kill -0 $(cat "$PID_FILE") 2>/dev/null; then
            echo "SSDP daemon already running (PID $(cat $PID_FILE))"
            exit 0
        fi
        nohup php "$DAEMON_PHP" > "$LOG_FILE" 2>&1 &
        echo $! > "$PID_FILE"
        sleep 1
        if kill -0 $(cat "$PID_FILE") 2>/dev/null; then
            echo "SSDP daemon started (PID $(cat $PID_FILE))"
        else
            echo "SSDP daemon failed to start - check $LOG_FILE"
        fi
        ;;
    stop)
        if [ -f "$PID_FILE" ]; then
            kill $(cat "$PID_FILE") 2>/dev/null
            rm -f "$PID_FILE"
            echo "SSDP daemon stopped"
        else
            pkill -f "ssdpd.php" 2>/dev/null && echo "SSDP daemon stopped" || echo "Not running"
        fi
        ;;
    restart)
        $0 stop
        sleep 1
        $0 start
        ;;
    status)
        if [ -f "$PID_FILE" ] && kill -0 $(cat "$PID_FILE") 2>/dev/null; then
            echo "SSDP daemon is running (PID $(cat $PID_FILE))"
            echo "Log: $LOG_FILE"
        elif pgrep -f "ssdpd.php" >/dev/null; then
            echo "SSDP daemon is running"
            echo "Log: $LOG_FILE"
        else
            echo "SSDP daemon is not running"
        fi
        ;;
    logs)
        tail -f "$LOG_FILE"
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|logs}"
        exit 1
        ;;
esac
