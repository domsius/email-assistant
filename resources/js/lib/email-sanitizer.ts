import DOMPurify from "dompurify";

// Configuration for DOMPurify email sanitization
export const EMAIL_SANITIZE_CONFIG = {
  ALLOWED_TAGS: [
    "p",
    "br",
    "span",
    "div",
    "a",
    "b",
    "i",
    "u",
    "strong",
    "em",
    "h1",
    "h2",
    "h3",
    "h4",
    "h5",
    "h6",
    "ul",
    "ol",
    "li",
    "table",
    "thead",
    "tbody",
    "tfoot",
    "tr",
    "td",
    "th",
    "img",
    "blockquote",
    "pre",
    "code",
    "hr",
    "style", // Move style here from ADD_TAGS
    "font", // Legacy email support
    "center", // Legacy email support
    "small",
    "big",
    "sub",
    "sup",
    "mark",
    "abbr",
    "address",
    "article",
    "aside",
    "footer",
    "header",
    "main",
    "nav",
    "section",
    "figure",
    "figcaption",
    "picture",
    "source",
    "time",
    "video",
    "audio",
    "track",
    "map",
    "area",
  ],
  ALLOWED_ATTR: [
    "href",
    "src",
    "srcset",
    "alt",
    "title",
    "width",
    "height",
    "style", // This is crucial for inline styles
    "class",
    "id",
    "target",
    "rel",
    "align",
    "valign",
    "bgcolor",
    "background",
    "border",
    "bordercolor",
    "cellpadding",
    "cellspacing",
    "color",
    "face",
    "size",
    "type",
    "start",
    "value",
    "compact",
    "noshade",
    "nowrap",
    "hspace",
    "vspace",
    "shape",
    "coords",
    "usemap",
    "frameborder",
    "scrolling",
    "longdesc",
    "marginwidth",
    "marginheight",
    "role",
    "aria-label",
    "aria-labelledby",
    "aria-describedby",
    "aria-hidden",
    "data-*", // Allow data attributes for some email clients
    "xml:lang",
    "lang",
    "dir",
    "media",
    "sizes",
    "crossorigin",
    "integrity",
    "loading",
    "decoding",
    "fetchpriority",
  ],
  ALLOW_DATA_ATTR: true, // Enable data attributes
  KEEP_CONTENT: true,
  ADD_ATTR: ["target", "rel"],
  FORCE_BODY: true,
  RETURN_DOM: false,
  RETURN_DOM_FRAGMENT: false,
  // Allow style tags and their content
  FORBID_TAGS: [],
  FORBID_ATTR: [],
  // This is critical for preserving style tags
  ADD_TAGS: ['style'],
  // Don't strip unknown tags
  CUSTOM_ELEMENT_HANDLING: {
    tagNameCheck: () => true,
    attributeNameCheck: () => true
  }
} as DOMPurify.Config;

/**
 * Sanitizes email HTML content for safe rendering
 * - Removes potentially dangerous elements and attributes
 * - Adds security attributes to links
 * - Preserves email formatting and styling
 */
export function sanitizeEmailContent(content: string): string {
  if (!content) return "";

  try {
    console.log('=== sanitizeEmailContent START ===');
    console.log('Input content preview:', content.substring(0, 200));
    console.log('Input has <style:', content.includes('<style'));
    console.log('Input has &lt;style:', content.includes('&lt;style'));
    
    // Decode HTML entities multiple times if needed (for double-encoded content)
    let decodedContent = content;
    
    // Keep decoding until no more encoded entities are found
    while (decodedContent.includes('&lt;') || decodedContent.includes('&gt;') || decodedContent.includes('&amp;')) {
      const textarea = document.createElement('textarea');
      textarea.innerHTML = decodedContent;
      const newDecoded = textarea.value;
      
      // Break if no change (prevent infinite loop)
      if (newDecoded === decodedContent) break;
      decodedContent = newDecoded;
    }
    
    // Extract style tags before sanitization to preserve them
    // Also check for escaped style tags
    const styleRegex = /<style[^>]*>([\s\S]*?)<\/style>/gi;
    const escapedStyleRegex = /&lt;style[^&]*&gt;([\s\S]*?)&lt;\/style&gt;/gi;
    const styles: string[] = [];
    let match;
    
    // First check if there are escaped style tags
    console.log('Checking for escaped styles:', decodedContent.includes('&lt;style'));
    
    // Collect all style tags
    while ((match = styleRegex.exec(decodedContent)) !== null) {
      styles.push(match[0]);
      console.log('Found style tag:', match[0].substring(0, 50) + '...');
    }
    
    // If no regular style tags found, check for escaped ones
    if (styles.length === 0 && decodedContent.includes('&lt;style')) {
      console.log('No regular style tags, but found escaped ones. Content might be double-encoded.');
      // Try one more level of decoding
      const textarea = document.createElement('textarea');
      textarea.innerHTML = decodedContent;
      const doubleDecoded = textarea.value;
      
      styleRegex.lastIndex = 0;
      while ((match = styleRegex.exec(doubleDecoded)) !== null) {
        styles.push(match[0]);
        console.log('Found style tag after double decode:', match[0].substring(0, 50) + '...');
      }
      
      if (styles.length > 0) {
        decodedContent = doubleDecoded;
      }
    }
    
    // Remove style tags temporarily
    const contentWithoutStyles = decodedContent.replace(styleRegex, '<!-- STYLE_PLACEHOLDER -->');
    
    // Sanitize the content without style tags
    let sanitized = DOMPurify.sanitize(contentWithoutStyles, EMAIL_SANITIZE_CONFIG);
    
    // Re-insert the style tags - do this carefully to ensure all placeholders are replaced
    let finalContent = sanitized;
    styles.forEach((styleTag, index) => {
      // Replace one placeholder at a time to ensure proper ordering
      finalContent = finalContent.replace('<!-- STYLE_PLACEHOLDER -->', styleTag);
    });
    
    console.log('Style preservation:', {
      stylesExtracted: styles.length,
      placeholdersInSanitized: (sanitized.match(/<!-- STYLE_PLACEHOLDER -->/g) || []).length,
      finalHasStyles: finalContent.includes('<style'),
      firstStyleInFinal: finalContent.indexOf('<style') !== -1 ? finalContent.substring(finalContent.indexOf('<style'), finalContent.indexOf('<style') + 100) : 'none'
    });
    
    sanitized = finalContent;
    
    // Parse the sanitized HTML for link processing
    const tempDiv = document.createElement("div");
    tempDiv.innerHTML = sanitized;
    
    // Use the sanitized content directly instead of parsing and re-serializing
    // This preserves our carefully reconstructed style tags
    const linkRegex = /<a\s+([^>]*?)>/gi;
    const processedContent = sanitized.replace(linkRegex, (match, attrs) => {
      // Add target and rel if not already present
      if (!attrs.includes('target=')) {
        attrs += ' target="_blank"';
      }
      if (!attrs.includes('rel=')) {
        attrs += ' rel="noopener noreferrer"';
      }
      return `<a ${attrs}>`;
    });
    
    console.log('=== sanitizeEmailContent END ===');
    console.log('Output has <style:', processedContent.includes('<style'));
    console.log('Output preview:', processedContent.substring(0, 200));
    
    return processedContent;
  } catch (error) {
    console.error("Error sanitizing email content:", error);
    // Return empty string on error for safety
    return "";
  }
}

/**
 * Sanitizes email content and returns both HTML and plain text versions
 */
export function sanitizeEmailWithPlainText(content: string): {
  html: string;
  plainText: string;
} {
  const html = sanitizeEmailContent(content);
  
  // Extract plain text from sanitized HTML
  const tempDiv = document.createElement("div");
  tempDiv.innerHTML = html;
  const plainText = tempDiv.textContent || tempDiv.innerText || "";
  
  return { html, plainText };
}