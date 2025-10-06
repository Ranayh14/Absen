#!/usr/bin/env python3
"""
FaceNet Security

This module provides security features for the FaceNet service.
"""

import os
import sys
import json
import time
import hashlib
import hmac
import secrets
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Tuple
import ipaddress

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

class FaceNetSecurity:
    """Security manager for FaceNet service."""
    
    def __init__(self, config_file='security_config.json'):
        """Initialize security manager."""
        self.config_file = config_file
        self.config = self.load_config()
        self.failed_attempts = {}  # Track failed attempts
        self.blocked_ips = set()   # Blocked IP addresses
        self.rate_limits = {}      # Rate limiting data
        self.session_tokens = {}   # Active session tokens
        
    def load_config(self) -> Dict:
        """Load security configuration."""
        default_config = {
            'max_failed_attempts': 5,
            'lockout_duration': 300,  # 5 minutes
            'rate_limit_requests': 100,
            'rate_limit_window': 3600,  # 1 hour
            'session_timeout': 1800,  # 30 minutes
            'allowed_ips': [],
            'blocked_ips': [],
            'require_https': False,
            'log_security_events': True,
            'encryption_key': secrets.token_hex(32)
        }
        
        if os.path.exists(self.config_file):
            try:
                with open(self.config_file, 'r') as f:
                    config = json.load(f)
                # Merge with defaults
                for key, value in default_config.items():
                    if key not in config:
                        config[key] = value
                return config
            except Exception as e:
                print(f"Error loading security config: {e}")
                return default_config
        else:
            # Create default config file
            self.save_config(default_config)
            return default_config
    
    def save_config(self, config: Dict = None):
        """Save security configuration."""
        if config is None:
            config = self.config
        
        try:
            with open(self.config_file, 'w') as f:
                json.dump(config, f, indent=2)
        except Exception as e:
            print(f"Error saving security config: {e}")
    
    def is_ip_allowed(self, ip_address: str) -> bool:
        """Check if IP address is allowed."""
        try:
            ip = ipaddress.ip_address(ip_address)
            
            # Check if IP is in blocked list
            if ip_address in self.blocked_ips:
                return False
            
            # Check if IP is in blocked config list
            for blocked_ip in self.config.get('blocked_ips', []):
                if ip in ipaddress.ip_network(blocked_ip, strict=False):
                    return False
            
            # Check if IP is in allowed list (if configured)
            allowed_ips = self.config.get('allowed_ips', [])
            if allowed_ips:
                for allowed_ip in allowed_ips:
                    if ip in ipaddress.ip_network(allowed_ip, strict=False):
                        return True
                return False
            
            return True
        except ValueError:
            return False
    
    def is_ip_blocked(self, ip_address: str) -> bool:
        """Check if IP address is blocked."""
        return ip_address in self.blocked_ips
    
    def block_ip(self, ip_address: str, duration: int = None):
        """Block an IP address."""
        if duration is None:
            duration = self.config.get('lockout_duration', 300)
        
        self.blocked_ips.add(ip_address)
        
        # Schedule unblock
        def unblock_ip():
            time.sleep(duration)
            self.blocked_ips.discard(ip_address)
        
        import threading
        thread = threading.Thread(target=unblock_ip)
        thread.daemon = True
        thread.start()
        
        self.log_security_event('ip_blocked', ip_address=ip_address, details=f'Blocked for {duration} seconds')
    
    def check_rate_limit(self, ip_address: str) -> bool:
        """Check if IP address is within rate limits."""
        current_time = time.time()
        window_start = current_time - self.config.get('rate_limit_window', 3600)
        
        # Clean old entries
        if ip_address in self.rate_limits:
            self.rate_limits[ip_address] = [
                timestamp for timestamp in self.rate_limits[ip_address]
                if timestamp > window_start
            ]
        else:
            self.rate_limits[ip_address] = []
        
        # Check rate limit
        request_count = len(self.rate_limits[ip_address])
        max_requests = self.config.get('rate_limit_requests', 100)
        
        if request_count >= max_requests:
            self.log_security_event('rate_limit_exceeded', ip_address=ip_address, 
                                  details=f'{request_count} requests in {self.config.get("rate_limit_window", 3600)} seconds')
            return False
        
        # Add current request
        self.rate_limits[ip_address].append(current_time)
        return True
    
    def check_failed_attempts(self, ip_address: str) -> bool:
        """Check if IP address has too many failed attempts."""
        current_time = time.time()
        lockout_duration = self.config.get('lockout_duration', 300)
        
        if ip_address in self.failed_attempts:
            attempts = self.failed_attempts[ip_address]
            
            # Clean old attempts
            attempts = [timestamp for timestamp in attempts if current_time - timestamp < lockout_duration]
            self.failed_attempts[ip_address] = attempts
            
            # Check if locked out
            max_attempts = self.config.get('max_failed_attempts', 5)
            if len(attempts) >= max_attempts:
                self.log_security_event('too_many_failed_attempts', ip_address=ip_address, 
                                      details=f'{len(attempts)} failed attempts')
                return False
        
        return True
    
    def record_failed_attempt(self, ip_address: str):
        """Record a failed attempt for an IP address."""
        current_time = time.time()
        
        if ip_address not in self.failed_attempts:
            self.failed_attempts[ip_address] = []
        
        self.failed_attempts[ip_address].append(current_time)
        
        # Check if should be blocked
        max_attempts = self.config.get('max_failed_attempts', 5)
        if len(self.failed_attempts[ip_address]) >= max_attempts:
            self.block_ip(ip_address)
    
    def generate_session_token(self, user_id: str, ip_address: str) -> str:
        """Generate a session token."""
        token_data = {
            'user_id': user_id,
            'ip_address': ip_address,
            'timestamp': time.time(),
            'random': secrets.token_hex(16)
        }
        
        # Create token
        token_string = json.dumps(token_data, sort_keys=True)
        token = hmac.new(
            self.config['encryption_key'].encode(),
            token_string.encode(),
            hashlib.sha256
        ).hexdigest()
        
        # Store token
        self.session_tokens[token] = {
            'user_id': user_id,
            'ip_address': ip_address,
            'created_at': time.time(),
            'last_used': time.time()
        }
        
        return token
    
    def validate_session_token(self, token: str, ip_address: str) -> Optional[str]:
        """Validate a session token."""
        if token not in self.session_tokens:
            return None
        
        session = self.session_tokens[token]
        current_time = time.time()
        
        # Check if token is expired
        session_timeout = self.config.get('session_timeout', 1800)
        if current_time - session['created_at'] > session_timeout:
            del self.session_tokens[token]
            return None
        
        # Check if IP address matches
        if session['ip_address'] != ip_address:
            self.log_security_event('token_ip_mismatch', ip_address=ip_address, 
                                  details=f'Token IP: {session["ip_address"]}')
            return None
        
        # Update last used time
        session['last_used'] = current_time
        
        return session['user_id']
    
    def revoke_session_token(self, token: str):
        """Revoke a session token."""
        if token in self.session_tokens:
            del self.session_tokens[token]
    
    def revoke_user_sessions(self, user_id: str):
        """Revoke all sessions for a user."""
        tokens_to_remove = []
        for token, session in self.session_tokens.items():
            if session['user_id'] == user_id:
                tokens_to_remove.append(token)
        
        for token in tokens_to_remove:
            del self.session_tokens[token]
    
    def cleanup_expired_sessions(self):
        """Clean up expired sessions."""
        current_time = time.time()
        session_timeout = self.config.get('session_timeout', 1800)
        
        expired_tokens = []
        for token, session in self.session_tokens.items():
            if current_time - session['created_at'] > session_timeout:
                expired_tokens.append(token)
        
        for token in expired_tokens:
            del self.session_tokens[token]
    
    def validate_image_data(self, image_data: str) -> bool:
        """Validate image data for security."""
        try:
            # Check if it's a valid base64 string
            if not image_data.startswith('data:image/'):
                return False
            
            # Extract base64 part
            if ',' not in image_data:
                return False
            
            header, data = image_data.split(',', 1)
            
            # Check image type
            if not any(img_type in header for img_type in ['jpeg', 'jpg', 'png', 'webp']):
                return False
            
            # Check data length (prevent extremely large images)
            max_size = 10 * 1024 * 1024  # 10MB
            if len(data) > max_size:
                return False
            
            # Try to decode base64
            import base64
            decoded = base64.b64decode(data)
            
            # Check if it's a valid image
            from PIL import Image
            import io
            Image.open(io.BytesIO(decoded))
            
            return True
        except Exception:
            return False
    
    def sanitize_input(self, input_string: str) -> str:
        """Sanitize input string."""
        if not isinstance(input_string, str):
            return str(input_string)
        
        # Remove null bytes
        input_string = input_string.replace('\x00', '')
        
        # Limit length
        max_length = 1000
        if len(input_string) > max_length:
            input_string = input_string[:max_length]
        
        # Remove potentially dangerous characters
        dangerous_chars = ['<', '>', '"', "'", '&', '\n', '\r', '\t']
        for char in dangerous_chars:
            input_string = input_string.replace(char, '')
        
        return input_string.strip()
    
    def log_security_event(self, event_type: str, user_id: str = None, ip_address: str = None, details: str = None):
        """Log security events."""
        if not self.config.get('log_security_events', True):
            return
        
        event_data = {
            'timestamp': datetime.now().isoformat(),
            'event_type': event_type,
            'user_id': user_id,
            'ip_address': ip_address,
            'details': details
        }
        
        # Log to file
        log_file = 'security_events.log'
        try:
            with open(log_file, 'a') as f:
                f.write(json.dumps(event_data) + '\n')
        except Exception as e:
            print(f"Error logging security event: {e}")
        
        # Also log to main logger if available
        try:
            from facenet_logs import log_security_event
            log_security_event(event_type, user_id, ip_address, details)
        except ImportError:
            pass
    
    def get_security_stats(self) -> Dict:
        """Get security statistics."""
        current_time = time.time()
        
        # Count active sessions
        active_sessions = len(self.session_tokens)
        
        # Count blocked IPs
        blocked_ips = len(self.blocked_ips)
        
        # Count rate limited IPs
        rate_limited_ips = 0
        for ip, timestamps in self.rate_limits.items():
            if len(timestamps) >= self.config.get('rate_limit_requests', 100):
                rate_limited_ips += 1
        
        # Count IPs with failed attempts
        failed_attempt_ips = 0
        for ip, attempts in self.failed_attempts.items():
            if len(attempts) >= self.config.get('max_failed_attempts', 5):
                failed_attempt_ips += 1
        
        return {
            'active_sessions': active_sessions,
            'blocked_ips': blocked_ips,
            'rate_limited_ips': rate_limited_ips,
            'failed_attempt_ips': failed_attempt_ips,
            'total_rate_limited_ips': len(self.rate_limits),
            'total_failed_attempt_ips': len(self.failed_attempts)
        }
    
    def is_request_allowed(self, ip_address: str, user_id: str = None) -> Tuple[bool, str]:
        """Check if a request is allowed."""
        # Check if IP is allowed
        if not self.is_ip_allowed(ip_address):
            return False, "IP address not allowed"
        
        # Check if IP is blocked
        if self.is_ip_blocked(ip_address):
            return False, "IP address is blocked"
        
        # Check rate limits
        if not self.check_rate_limit(ip_address):
            return False, "Rate limit exceeded"
        
        # Check failed attempts
        if not self.check_failed_attempts(ip_address):
            return False, "Too many failed attempts"
        
        return True, "Request allowed"

# Global security instance
security = FaceNetSecurity()

def get_security():
    """Get the global security instance."""
    return security

def is_request_allowed(ip_address: str, user_id: str = None) -> Tuple[bool, str]:
    """Check if a request is allowed using global security instance."""
    return security.is_request_allowed(ip_address, user_id)

def validate_image_data(image_data: str) -> bool:
    """Validate image data using global security instance."""
    return security.validate_image_data(image_data)

def sanitize_input(input_string: str) -> str:
    """Sanitize input using global security instance."""
    return security.sanitize_input(input_string)

def log_security_event(event_type: str, user_id: str = None, ip_address: str = None, details: str = None):
    """Log security event using global security instance."""
    security.log_security_event(event_type, user_id, ip_address, details)

def main():
    """Main function for testing security."""
    print("FaceNet Security System Test")
    print("=" * 50)
    
    # Test IP validation
    test_ips = ['192.168.1.1', '10.0.0.1', '127.0.0.1', '8.8.8.8']
    for ip in test_ips:
        allowed, reason = security.is_request_allowed(ip)
        print(f"IP {ip}: {'Allowed' if allowed else 'Blocked'} - {reason}")
    
    # Test rate limiting
    print("\nTesting rate limiting...")
    test_ip = '192.168.1.100'
    for i in range(5):
        allowed, reason = security.is_request_allowed(test_ip)
        print(f"Request {i+1}: {'Allowed' if allowed else 'Blocked'} - {reason}")
    
    # Test session tokens
    print("\nTesting session tokens...")
    token = security.generate_session_token('user123', '192.168.1.1')
    print(f"Generated token: {token[:20]}...")
    
    user_id = security.validate_session_token(token, '192.168.1.1')
    print(f"Validated token for user: {user_id}")
    
    # Test image validation
    print("\nTesting image validation...")
    test_image = "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A"
    is_valid = security.validate_image_data(test_image)
    print(f"Image validation: {'Valid' if is_valid else 'Invalid'}")
    
    # Test input sanitization
    print("\nTesting input sanitization...")
    test_inputs = [
        "Normal input",
        "Input with <script>alert('xss')</script>",
        "Input with null\x00byte",
        "Very long input " * 100
    ]
    
    for test_input in test_inputs:
        sanitized = security.sanitize_input(test_input)
        print(f"Original: {test_input[:50]}...")
        print(f"Sanitized: {sanitized[:50]}...")
        print()
    
    # Get security stats
    stats = security.get_security_stats()
    print("Security Statistics:")
    for key, value in stats.items():
        print(f"  {key}: {value}")
    
    print("\nSecurity test completed!")

if __name__ == '__main__':
    main()
