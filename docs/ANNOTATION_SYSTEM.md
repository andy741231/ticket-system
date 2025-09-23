# Ticket Annotation System

## Overview

The Ticket Annotation System is a comprehensive solution that allows users to add visual annotations to images within the ticketing platform. Users can capture screenshots from URLs, upload files, and create interactive annotations with approval workflows.

## Features

### Input Methods
- **URL Screenshot Capture**: Automatically capture full-page screenshots of websites using Puppeteer
- **File Upload**: Upload images, PDFs, and other files with automatic conversion to images

### Screenshot Generation
- Headless browser automation using Puppeteer
- Full-page screenshot capture with configurable viewport sizes
- Automatic retry and error handling
- Support for various image formats

### Image Storage
- Organized storage in `/storage/app/public/tickets/{ticket_id}/images/`
- Automatic directory creation and cleanup
- File size optimization and format standardization
- Metadata tracking for processing status

### Interactive Annotation Tools
- **Point Annotations**: Click to add point markers with comments
- **Rectangle Tool**: Draw rectangular selections
- **Circle Tool**: Create circular annotations
- **Arrow Tool**: Draw directional arrows
- **Freehand Drawing**: Free-form drawing capabilities
- **Text Annotations**: Add text comments at specific locations

### Annotation Features
- Real-time canvas-based drawing interface
- Customizable colors and stroke widths
- Annotation persistence and editing
- User attribution and timestamps
- Status tracking (pending, approved, rejected)

### Approval/Rejection Workflow
- Admin-only approval/rejection controls
- Review notes and feedback system
- Status badges and visual indicators
- Audit trail for all annotation changes

## Database Schema

### ticket_images
- `id` - Primary key
- `ticket_id` - Foreign key to tickets table
- `source_type` - 'url' or 'file'
- `source_value` - Original URL or filename
- `image_path` - Path to processed image
- `original_name` - Original filename (for uploads)
- `mime_type` - Image MIME type
- `size` - File size in bytes
- `width` - Image width in pixels
- `height` - Image height in pixels
- `status` - 'processing', 'completed', 'failed'
- `error_message` - Error details if processing failed
- `metadata` - JSON metadata (viewport size, etc.)
- `created_at` / `updated_at` - Timestamps

### annotations
- `id` - Primary key
- `ticket_image_id` - Foreign key to ticket_images
- `user_id` - Foreign key to users (creator)
- `type` - Annotation type (point, rectangle, circle, arrow, freehand, text)
- `coordinates` - JSON coordinates and shape data
- `content` - Text content for text annotations
- `style` - JSON style data (color, stroke width, etc.)
- `status` - 'pending', 'approved', 'rejected'
- `reviewed_by` - Foreign key to users (reviewer)
- `reviewed_at` - Review timestamp
- `review_notes` - Review feedback
- `created_at` / `updated_at` - Timestamps

### annotation_comments
- `id` - Primary key
- `annotation_id` - Foreign key to annotations
- `user_id` - Foreign key to users
- `content` - Comment text
- `parent_id` - Foreign key for threaded comments
- `created_at` / `updated_at` - Timestamps

## API Endpoints

### Image Management
- `GET /api/tickets/{ticket}/images` - List all images for a ticket
- `POST /api/tickets/{ticket}/images/from-url` - Create image from URL
- `POST /api/tickets/{ticket}/images/from-file` - Upload and process file
- `GET /api/tickets/{ticket}/images/{image}` - Get specific image with annotations
- `DELETE /api/tickets/{ticket}/images/{image}` - Delete image
- `GET /api/tickets/{ticket}/images/{image}/status` - Check processing status

### Annotation Management
- `GET /api/tickets/{ticket}/images/{image}/annotations` - List annotations
- `POST /api/tickets/{ticket}/images/{image}/annotations` - Create annotation
- `GET /api/tickets/{ticket}/images/{image}/annotations/{annotation}` - Get annotation
- `PUT /api/tickets/{ticket}/images/{image}/annotations/{annotation}` - Update annotation
- `DELETE /api/tickets/{ticket}/images/{image}/annotations/{annotation}` - Delete annotation
- `PUT /api/tickets/{ticket}/images/{image}/annotations/{annotation}/status` - Approve/reject

### Comment Management
- `GET /api/tickets/{ticket}/images/{image}/annotations/{annotation}/comments` - List comments
- `POST /api/tickets/{ticket}/images/{image}/annotations/{annotation}/comments` - Add comment
- `DELETE /api/tickets/{ticket}/images/{image}/annotations/{annotation}/comments/{comment}` - Delete comment

## Frontend Components

### AnnotationCanvas.vue
- Canvas-based drawing interface
- Tool selection and styling controls
- Real-time annotation rendering
- Mouse/touch event handling
- Annotation marker overlays

### AnnotationInterface.vue
- Main annotation management interface
- Image upload and URL capture forms
- Processing status indicators
- Annotation list and management
- Comment system integration

## Usage

### Adding Images to Tickets

1. **From URL**: Enter a website URL and click "Capture" to generate a screenshot
2. **From File**: Upload an image, PDF, or other supported file type

### Creating Annotations

1. Select an annotation tool (Point, Rectangle, Circle, Arrow, Freehand, Text)
2. Choose color and stroke width
3. Click or draw on the image to create the annotation
4. Add text content if using text annotations

### Managing Annotations

- **Edit**: Click the edit button on any annotation you created
- **Delete**: Click the delete button to remove annotations
- **Comment**: Add comments to discuss specific annotations
- **Review**: Admins can approve or reject annotations with notes

### Approval Workflow

1. Users create annotations (status: pending)
2. Admins review annotations and either:
   - Approve with optional notes
   - Reject with required reason
3. Status badges show current approval state
4. Audit trail tracks all changes

## Technical Implementation

### Screenshot Capture
- Node.js script using Puppeteer for browser automation
- Configurable viewport sizes and wait times
- Error handling and retry logic
- JSON output for integration with PHP backend

### Image Processing
- Intervention Image library for PHP
- Automatic format conversion to PNG
- Size optimization for large images
- Placeholder generation for unsupported files

### Canvas Drawing
- HTML5 Canvas API for real-time drawing
- Vue.js reactive components
- Coordinate system mapping
- Export/import of annotation data

### Security Considerations
- Permission-based access control
- CSRF protection on all API endpoints
- File upload validation and sanitization
- User attribution and audit logging

## Installation Requirements

### Backend Dependencies
- PHP 8.2+
- Laravel 12.0+
- Intervention Image 3.x
- MySQL/PostgreSQL database

### Frontend Dependencies
- Vue.js 3.x
- Inertia.js
- Canvas API support

### System Dependencies
- Node.js 20+ (for screenshot capture)
- Puppeteer and Chrome/Chromium
- ImageMagick (optional, for PDF conversion)

## Configuration

### Environment Variables
```env
# Screenshot capture settings
SCREENSHOT_TIMEOUT=30000
SCREENSHOT_VIEWPORT_WIDTH=1920
SCREENSHOT_VIEWPORT_HEIGHT=1080

# Image processing settings
MAX_IMAGE_SIZE=10240  # KB
MAX_IMAGE_DIMENSION=2000  # pixels
```

### Storage Configuration
Ensure the `storage/app/public` directory is writable and linked:
```bash
php artisan storage:link
```

## Troubleshooting

### Common Issues

1. **Screenshot capture fails**
   - Check Node.js and Puppeteer installation
   - Verify network connectivity
   - Check file permissions on storage directory

2. **Image processing errors**
   - Ensure Intervention Image is properly installed
   - Check file upload limits in PHP configuration
   - Verify storage directory permissions

3. **Canvas drawing not working**
   - Check browser Canvas API support
   - Verify JavaScript is enabled
   - Check for console errors in browser

### Performance Optimization

1. **Large Images**: Automatic resizing prevents memory issues
2. **Screenshot Queue**: Consider implementing job queues for high-volume usage
3. **Caching**: Browser caching for processed images
4. **CDN**: Consider CDN for image delivery in production

## Future Enhancements

- Real-time collaboration on annotations
- Advanced drawing tools (shapes, text formatting)
- Annotation templates and presets
- Export annotations to PDF reports
- Integration with external image sources
- Mobile-optimized touch interface
- Bulk annotation operations
- Advanced search and filtering
