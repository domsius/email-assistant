import React, { useEffect, useRef, useState } from "react";
import { cn } from "@/lib/utils";
import { sanitizeEmailContent } from "@/lib/email-sanitizer";

interface EmailContentRendererProps {
  htmlContent: string;
  className?: string;
  enableIframeIsolation?: boolean;
}

export function EmailContentRenderer({
  htmlContent,
  className,
  enableIframeIsolation = true,
}: EmailContentRendererProps) {
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const [iframeHeight, setIframeHeight] = useState(600);
  
  // Process content once for both iframe and fallback rendering
  const processedContent = React.useMemo(() => {
    // First decode any HTML entities
    let decoded = htmlContent;
    while (decoded.includes('&lt;') || decoded.includes('&gt;') || decoded.includes('&amp;')) {
      const textarea = document.createElement('textarea');
      textarea.innerHTML = decoded;
      const newDecoded = textarea.value;
      if (newDecoded === decoded) break;
      decoded = newDecoded;
    }
    
    // Extract style tags BEFORE sanitization
    const styleRegex = /<style[^>]*>([\s\S]*?)<\/style>/gi;
    const styles: string[] = [];
    let match;
    
    while ((match = styleRegex.exec(decoded)) !== null) {
      styles.push(match[0]);
    }
    
    // Sanitize the content (this will remove style tags)
    const sanitized = sanitizeEmailContent(decoded);
    
    return {
      sanitized,
      styles,
      decoded
    };
  }, [htmlContent]);

  useEffect(() => {
    if (!enableIframeIsolation || !iframeRef.current) return;

    const iframe = iframeRef.current;
    const iframeDoc = iframe.contentDocument || iframe.contentWindow?.document;

    if (!iframeDoc) return;
    
    const { sanitized, styles } = processedContent;
    
    // Create the HTML document with email content
    const htmlDocument = `
      <!DOCTYPE html>
      <html>
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <meta http-equiv="Content-Security-Policy" content="default-src 'self' 'unsafe-inline' data: https: http:; img-src * data: blob: https: http:; style-src 'unsafe-inline' *;">
          ${styles.join('\n')}
          <style>
            /* Reset styles for email content */
            body {
              margin: 0;
              padding: 16px;
              font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
              font-size: 14px;
              line-height: 1.5;
              color: #333;
              background-color: transparent;
              word-wrap: break-word;
              overflow-wrap: break-word;
            }
            
            /* Ensure images don't overflow */
            img {
              max-width: 100% !important;
              height: auto !important;
            }
            
            /* Ensure tables don't overflow */
            table {
              max-width: 100% !important;
              table-layout: fixed;
              word-wrap: break-word;
            }
            
            /* Handle pre/code blocks */
            pre, code {
              white-space: pre-wrap;
              word-wrap: break-word;
            }
            
            /* Link styling */
            a {
              color: #0066cc;
              text-decoration: underline;
            }
            
            a:hover {
              color: #0052a3;
            }
          </style>
        </head>
        <body>
          ${sanitized}
        </body>
      </html>
    `;

    // Write the content to iframe
    iframeDoc.open();
    iframeDoc.write(htmlDocument);
    iframeDoc.close();
    
    // Debug images if development mode
    if (process.env.NODE_ENV === 'development') {
      const imgMatches = sanitized.match(/<img[^>]+>/gi);
      if (imgMatches && imgMatches.length > 0) {
        console.log(`Email contains ${imgMatches.length} images`);
      }
    }

    // Adjust iframe height based on content
    const adjustHeight = () => {
      const body = iframeDoc.body;
      const html = iframeDoc.documentElement;
      
      if (body && html) {
        const height = Math.max(
          body.scrollHeight,
          body.offsetHeight,
          html.clientHeight,
          html.scrollHeight,
          html.offsetHeight
        );
        
        setIframeHeight(height + 32); // Add padding
      }
    };

    // Wait for content to load
    iframe.onload = adjustHeight;
    
    // Also adjust on window resize
    const resizeObserver = new ResizeObserver(adjustHeight);
    if (iframeDoc.body) {
      resizeObserver.observe(iframeDoc.body);
    }

    // Adjust height after images load
    const images = iframeDoc.getElementsByTagName("img");
    Array.from(images).forEach((img) => {
      // Store original src
      const originalSrc = img.src;
      
      img.onload = adjustHeight;
      img.onerror = () => {
        console.error(`Failed to load image: ${originalSrc}`);
        // Try to add a placeholder or alt text
        if (img.alt) {
          img.style.display = 'inline-block';
          img.style.backgroundColor = '#f3f4f6';
          img.style.padding = '20px';
          img.style.border = '1px solid #e5e7eb';
          img.style.borderRadius = '4px';
          img.style.minHeight = '100px';
          img.style.minWidth = '100px';
          img.style.textAlign = 'center';
          img.style.color = '#6b7280';
        }
      };
      
      // Add loading attribute for better performance
      img.loading = 'lazy';
    });

    // Initial adjustment
    setTimeout(adjustHeight, 100);

    return () => {
      resizeObserver.disconnect();
    };
  }, [enableIframeIsolation, processedContent]);

  if (enableIframeIsolation) {
    return (
      <iframe
        ref={iframeRef}
        className={cn(
          "w-full border-0 overflow-hidden",
          className
        )}
        height={iframeHeight}
        sandbox="allow-same-origin allow-popups"
        title="Email Content"
        loading="eager"
      />
    );
  }

  // Fallback to div rendering (less secure but preserves some styles)
  return (
    <div
      className={cn("email-content", className)}
      dangerouslySetInnerHTML={{ __html: processedContent.sanitized }}
      style={{
        // Reset conflicting styles
        all: "initial",
        fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
        fontSize: "14px",
        lineHeight: "1.5",
        color: "#333",
        display: "block",
      }}
    />
  );
}