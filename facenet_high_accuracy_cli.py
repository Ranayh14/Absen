#!/usr/bin/env python3
"""
FaceNet High Accuracy CLI

Command-line interface for the high-accuracy FaceNet service.
"""

import sys
import os
import json
import argparse
import logging
from typing import Dict, Optional

# Add the current directory to Python path
sys.path.insert(0, os.path.dirname(__file__))

try:
    from facenet_high_accuracy_service import (
        process_high_accuracy_attendance,
        generate_high_accuracy_embedding,
        get_high_accuracy_performance_stats,
        update_high_accuracy_thresholds,
        validate_face_quality
    )
except ImportError as e:
    print(json.dumps({
        'success': False,
        'error': f'Import error: {str(e)}',
        'error_code': 'IMPORT_ERROR'
    }))
    sys.exit(1)

# Configure logging
logging.basicConfig(level=logging.ERROR)  # Reduce log verbosity for CLI
logger = logging.getLogger(__name__)

def main():
    """Main CLI function."""
    parser = argparse.ArgumentParser(description='FaceNet High Accuracy CLI')
    parser.add_argument('--action', required=True, help='Action to perform')
    parser.add_argument('--image', help='Base64 encoded image')
    parser.add_argument('--user_id', help='User ID')
    parser.add_argument('--thresholds', help='JSON string of thresholds to update')
    
    args = parser.parse_args()
    
    try:
        if args.action == 'process_high_accuracy_attendance':
            if not args.image:
                print(json.dumps({
                    'success': False,
                    'error': 'Image is required for attendance processing',
                    'error_code': 'MISSING_IMAGE'
                }))
                sys.exit(1)
            
            user_id = int(args.user_id) if args.user_id else None
            result = process_high_accuracy_attendance(args.image, user_id)
            print(json.dumps(result))
            
        elif args.action == 'generate_high_accuracy_embedding':
            if not args.image or not args.user_id:
                print(json.dumps({
                    'success': False,
                    'error': 'Image and user_id are required for embedding generation',
                    'error_code': 'MISSING_PARAMETERS'
                }))
                sys.exit(1)
            
            user_id = int(args.user_id)
            result = generate_high_accuracy_embedding(args.image, user_id)
            print(json.dumps(result))
            
        elif args.action == 'get_performance_stats':
            result = get_high_accuracy_performance_stats()
            print(json.dumps(result))
            
        elif args.action == 'update_thresholds':
            if not args.thresholds:
                print(json.dumps({
                    'success': False,
                    'error': 'Thresholds are required for update',
                    'error_code': 'MISSING_THRESHOLDS'
                }))
                sys.exit(1)
            
            try:
                thresholds = json.loads(args.thresholds)
                result = update_high_accuracy_thresholds(thresholds)
                print(json.dumps(result))
            except json.JSONDecodeError:
                print(json.dumps({
                    'success': False,
                    'error': 'Invalid JSON format for thresholds',
                    'error_code': 'INVALID_JSON'
                }))
                sys.exit(1)
            
        elif args.action == 'validate_face_quality':
            if not args.image:
                print(json.dumps({
                    'success': False,
                    'error': 'Image is required for quality validation',
                    'error_code': 'MISSING_IMAGE'
                }))
                sys.exit(1)
            
            result = validate_face_quality(args.image)
            print(json.dumps(result))
            
        else:
            print(json.dumps({
                'success': False,
                'error': f'Unknown action: {args.action}',
                'error_code': 'UNKNOWN_ACTION'
            }))
            sys.exit(1)
            
    except Exception as e:
        logger.error(f"CLI error: {e}")
        print(json.dumps({
            'success': False,
            'error': f'CLI processing error: {str(e)}',
            'error_code': 'CLI_ERROR'
        }))
        sys.exit(1)

if __name__ == '__main__':
    main()
