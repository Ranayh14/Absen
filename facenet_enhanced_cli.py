#!/usr/bin/env python3
"""
FaceNet Enhanced Command Line Interface (CLI)

This script acts as a bridge between PHP and the EnhancedFaceNetService.
It receives JSON arguments from the command line, executes the
corresponding enhanced FaceNet service method, and prints the JSON result to stdout.
"""

import sys
import json
import os

# Add the directory containing facenet_enhanced_service.py to the Python path
sys.path.insert(0, os.path.dirname(__file__))

try:
    from facenet_enhanced_service import enhanced_service
except ImportError as e:
    print(json.dumps({'success': False, 'error': f'Failed to import EnhancedFaceNetService: {e}'}))
    sys.exit(1)

def main():
    if len(sys.argv) < 2:
        print(json.dumps({'success': False, 'error': 'No arguments provided'}))
        sys.exit(1)

    try:
        # Arguments are passed as a single JSON string
        args = json.loads(sys.argv[1])
        action = args.get('action')
        image = args.get('image')
        threshold = args.get('threshold')
        user_id = args.get('user_id') # For save_enhanced_embedding
    except json.JSONDecodeError:
        print(json.dumps({'success': False, 'error': 'Invalid JSON arguments'}))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({'success': False, 'error': f'Error parsing arguments: {e}'}))
        sys.exit(1)

    if not action:
        print(json.dumps({'success': False, 'error': 'Action is required'}))
        sys.exit(1)
    
    # Execute the requested action
    if action == 'generate_enhanced_embedding':
        result = enhanced_service.generate_enhanced_embedding(image)
        if result:
            print(json.dumps({
                'success': True,
                'data': {
                    'enhanced_embedding': result
                }
            }))
        else:
            print(json.dumps({
                'success': False,
                'error': 'Failed to generate enhanced embedding'
            }))
    
    elif action == 'save_enhanced_embedding':
        if not user_id:
            print(json.dumps({
                'success': False,
                'error': 'User ID is required for saving enhanced embedding'
            }))
            sys.exit(1)
        
        # Generate enhanced embedding first
        enhanced_embedding = enhanced_service.generate_enhanced_embedding(image)
        if enhanced_embedding:
            # Save to database
            success = enhanced_service.save_enhanced_embedding_to_database(int(user_id), enhanced_embedding)
            if success:
                print(json.dumps({
                    'success': True,
                    'message': 'Enhanced embedding generated and saved successfully'
                }))
            else:
                print(json.dumps({
                    'success': False,
                    'error': 'Failed to save enhanced embedding to database'
                }))
        else:
            print(json.dumps({
                'success': False,
                'error': 'Failed to generate enhanced embedding'
            }))
    
    elif action == 'recognize_enhanced_face':
        result = enhanced_service.recognize_enhanced_face(image, threshold)
        if result:
            print(json.dumps({
                'success': True,
                'data': result
            }))
        else:
            print(json.dumps({
                'success': False,
                'error': 'Enhanced face recognition failed'
            }))
    
    elif action == 'process_enhanced_attendance':
        result = enhanced_service.process_enhanced_attendance(image, threshold)
        if result:
            print(json.dumps({
                'success': True,
                'data': result['data'] # process_enhanced_attendance returns {'success': bool, 'data': recognition_result}
            }))
        else:
            print(json.dumps({
                'success': False,
                'error': 'Enhanced attendance processing failed'
            }))
    
    else:
        print(json.dumps({'success': False, 'error': f'Unknown action: {action}'}))

if __name__ == '__main__':
    main()
