#!/usr/bin/env python3
"""
Optimized FaceNet CLI - iPhone-like Performance

Command-line interface for the optimized FaceNet service.
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
    from facenet_optimized_service import (
        recognize_face_optimized,
        generate_embedding_optimized,
        get_optimized_performance_stats,
        clear_optimization_caches
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
    parser = argparse.ArgumentParser(description='Optimized FaceNet CLI - iPhone-like Performance')
    parser.add_argument('--action', required=True, help='Action to perform')
    parser.add_argument('--image', help='Base64 encoded image')
    parser.add_argument('--threshold', type=float, default=0.5, help='Recognition threshold')
    
    args = parser.parse_args()
    
    try:
        if args.action == 'recognize_face_optimized':
            if not args.image:
                print(json.dumps({
                    'success': False,
                    'error': 'Image is required for face recognition',
                    'error_code': 'MISSING_IMAGE'
                }))
                sys.exit(1)
            
            result = recognize_face_optimized(args.image, args.threshold)
            print(json.dumps(result))
            
        elif args.action == 'generate_embedding_optimized':
            if not args.image:
                print(json.dumps({
                    'success': False,
                    'error': 'Image is required for embedding generation',
                    'error_code': 'MISSING_IMAGE'
                }))
                sys.exit(1)
            
            result = generate_embedding_optimized(args.image)
            print(json.dumps(result))
            
        elif args.action == 'get_performance_stats':
            result = get_optimized_performance_stats()
            print(json.dumps({
                'success': True,
                'data': result
            }))
            
        elif args.action == 'clear_caches':
            clear_optimization_caches()
            print(json.dumps({
                'success': True,
                'message': 'Caches cleared successfully'
            }))
            
        elif args.action == 'process_attendance_optimized':
            if not args.image:
                print(json.dumps({
                    'success': False,
                    'error': 'Image is required for attendance processing',
                    'error_code': 'MISSING_IMAGE'
                }))
                sys.exit(1)
            
            # Process attendance with optimized recognition
            recognition_result = recognize_face_optimized(args.image, args.threshold)
            
            if recognition_result.get('success') and recognition_result.get('recognized'):
                # Format attendance result
                attendance_result = {
                    'success': True,
                    'data': {
                        'nim': recognition_result.get('nim'),
                        'nama': recognition_result.get('nama'),
                        'user_id': recognition_result.get('user_id'),
                        'confidence': recognition_result.get('confidence'),
                        'similarity': recognition_result.get('similarity'),
                        'processing_time': recognition_result.get('processing_time'),
                        'face_info': recognition_result.get('face_info')
                    }
                }
                print(json.dumps(attendance_result))
            else:
                print(json.dumps({
                    'success': False,
                    'error': recognition_result.get('error', 'Face not recognized'),
                    'error_code': 'FACE_NOT_RECOGNIZED',
                    'processing_time': recognition_result.get('processing_time', 0)
                }))
            
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
