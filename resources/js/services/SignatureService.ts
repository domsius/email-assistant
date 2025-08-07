import { authenticatedFetch } from '@/lib/utils';
import DOMPurify from 'dompurify';

/**
 * Centralized service for handling email signatures securely
 * Eliminates code duplication and provides safe HTML processing
 */
export class SignatureService {
  /**
   * Safely decode HTML entities without XSS risks
   * Replaces dangerous innerHTML approach with secure parsing
   */
  static decodeHtmlEntities(html: string): string {
    if (!html || typeof html !== 'string') {
      return '';
    }

    // Only decode if HTML entities are present
    if (!html.includes('&lt;') && !html.includes('&gt;') && !html.includes('&amp;')) {
      return html;
    }

    try {
      // Use DOMParser instead of dangerous innerHTML
      const parser = new DOMParser();
      const doc = parser.parseFromString(`<div>${html}</div>`, 'text/html');
      return doc.querySelector('div')?.textContent || html;
    } catch (error) {
      console.error('Failed to decode HTML entities:', error);
      return html; // Fallback to original if parsing fails
    }
  }

  /**
   * Sanitize signature HTML to prevent XSS attacks
   * Allows only safe HTML elements and attributes
   */
  static sanitizeSignature(rawSignature: string): string {
    if (!rawSignature || typeof rawSignature !== 'string') {
      return '';
    }

    // First decode HTML entities safely
    const decodedSignature = this.decodeHtmlEntities(rawSignature);

    // Then sanitize the HTML content
    return DOMPurify.sanitize(decodedSignature, {
      ALLOWED_TAGS: [
        'div', 'span', 'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'table', 'tbody', 'tr', 'td', 'th',
        'a', 'img'
      ],
      ALLOWED_ATTR: [
        'style', 'class', 'id',
        'href', 'target', 'rel',
        'src', 'alt', 'width', 'height',
        'align', 'valign',
        'dir'
      ],
      ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i,
      KEEP_CONTENT: true,
      RETURN_DOM: false,
      RETURN_DOM_FRAGMENT: false,
    });
  }

  /**
   * Fetch signature from API for a specific email account and address
   */
  static async fetchSignature(accountId: number, fromAddress: string): Promise<string> {
    if (!accountId || !fromAddress) {
      throw new Error('Account ID and from address are required');
    }

    try {
      const response = await authenticatedFetch(
        `/api/email-accounts/${accountId}/signature?from_address=${encodeURIComponent(fromAddress)}`
      );
      
      if (!response.ok) {
        throw new Error(`Failed to fetch signature: ${response.status} ${response.statusText}`);
      }

      const data = await response.json();
      
      if (!data.signature) {
        return ''; // No signature configured
      }

      // Log the raw signature before and after sanitization
      console.log('=== SIGNATURE API DEBUG ===');
      console.log('Raw signature from API:', data.signature);
      
      const sanitized = this.sanitizeSignature(data.signature);
      console.log('Sanitized signature:', sanitized);
      
      return sanitized;
    } catch (error) {
      console.error('Failed to fetch signature:', error);
      throw error; // Re-throw for caller to handle
    }
  }

  /**
   * Safely insert signature into email content
   * Handles different content formats and edge cases
   */
  static insertSignature(existingContent: string, signature: string): string {
    if (!signature || signature.trim() === '') {
      return existingContent || '';
    }

    // Validate inputs
    const content = existingContent || '';
    const trimmedContent = content.trim();

    // Empty content - add signature with proper structure
    if (!trimmedContent || trimmedContent === '<p></p>') {
      return `<p><br><br></p>${signature}`;
    }

    // Content with closing </p> tag
    if (content.endsWith('</p>')) {
      return content.slice(0, -4) + '<br><br>' + signature + '</p>';
    }

    // Other content - just append
    return content + '<br><br>' + signature;
  }

  /**
   * Get default signature when fetch fails
   * Provides graceful fallback for better UX
   */
  static getDefaultSignature(): string {
    return ''; // Empty signature as safe fallback
  }

  /**
   * Validate signature content for basic safety checks
   */
  static validateSignature(signature: string): { isValid: boolean; errors: string[] } {
    const errors: string[] = [];

    if (typeof signature !== 'string') {
      errors.push('Signature must be a string');
    }

    if (signature.length > 10000) {
      errors.push('Signature too long (max 10KB)');
    }

    // Check for potentially dangerous content
    const dangerousPatterns = [
      /<script/i,
      /javascript:/i,
      /on\w+\s*=/i, // onclick, onload, etc.
      /<iframe/i,
      /<object/i,
      /<embed/i,
    ];

    for (const pattern of dangerousPatterns) {
      if (pattern.test(signature)) {
        errors.push(`Potentially unsafe content detected: ${pattern.source}`);
      }
    }

    return {
      isValid: errors.length === 0,
      errors
    };
  }
}

// Export as default for easier importing
export default SignatureService;