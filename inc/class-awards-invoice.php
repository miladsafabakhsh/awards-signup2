<?php
/**
 * Awards Invoice Template
 * 
 * This class extends FPDF to create custom branded invoices
 * Save this as class-awards-invoice.php in the inc directory
 */

// Make sure FPDF is loaded
if (!class_exists('FPDF')) {
    require_once(plugin_dir_path(dirname(__FILE__)) . 'inc/vendor/fpdf/fpdf.php');
}

class Awards_Invoice extends FPDF {
    // Private properties
    private $entry_id;
    private $payment_details;
    private $user_data;
    private $entry_data;
    private $currency_symbol;
    private $currency_code;
    private $logo_path;
    
    /**
     * Constructor
     * 
     * @param int $entry_id Entry ID
     * @param array $payment_details Payment details
     * @param array $options Additional options
     */
    public function __construct($entry_id, $payment_details, $options = array()) {
        // Initialize FPDF
        parent::__construct('P', 'mm', 'A4');
        
        // Set properties
        $this->entry_id = $entry_id;
        $this->payment_details = $payment_details;
        
        // Set default options
        $default_options = array(
            'logo_path' => null,
            'color' => array(0, 102, 204), // RGB color for header and accent elements
        );
        
        // Merge options
        $options = array_merge($default_options, $options);
        
        // Set logo path
        $this->logo_path = $options['logo_path'];
        
        // Set color
        $this->SetDrawColor($options['color'][0], $options['color'][1], $options['color'][2]);
        $this->SetTextColor($options['color'][0], $options['color'][1], $options['color'][2]);
        
        // Load entry and user data
        $this->load_data();
        
        // Set document information
        $this->SetTitle(sprintf(__('Invoice - %s', 'awards'), $this->entry_data->post_title));
        $this->SetAuthor(get_bloginfo('name'));
        $this->SetCreator('Awards Photography Contest Plugin');
    }
    
    /**
     * Load entry and user data
     */
    private function load_data() {
        // Get entry data
        $this->entry_data = get_post($this->entry_id);
        
        if (!$this->entry_data) {
            throw new Exception(__('Entry not found', 'awards'));
        }
        
        // Get user data
        $user_id = $this->entry_data->post_author;
        $this->user_data = get_userdata($user_id);
        
        if (!$this->user_data) {
            throw new Exception(__('User not found', 'awards'));
        }
        
        // Set currency based on user country and default settings
        $user_country = get_user_meta($user_id, 'country', true);
        $default_currency = awards_options('currency');
        
        if ($user_country === 'Iran (Islamic Republic of)') {
            $this->currency_code = 'IRR';
            $this->currency_symbol = 'IRR ';
        } else if ($default_currency === 'euro' || $default_currency === 'eur') {
            $this->currency_code = 'EUR';
            $this->currency_symbol = '€';
        } else {
            $this->currency_code = 'USD';
            $this->currency_symbol = '$';
        }
    }
    
    /**
     * Generate the invoice
     * 
     * @return string Path to the generated PDF file
     */
    public function generate() {
        // Add the first page
        $this->AddPage();
        
        // Create header
        $this->create_header();
        
        // Add invoice information
        $this->create_invoice_info();
        
        // Add customer information
        $this->create_customer_info();
        
        // Add entry details
        $this->create_entry_details();
        
        // Add payment summary
        $this->create_payment_summary();
        
        // Add transaction details
        $this->create_transaction_details();
        
        // Add footer
        $this->create_footer();
        
        // Generate file name and path
        $upload_dir = wp_upload_dir();
        $invoice_dir = $upload_dir['basedir'] . '/awards-invoices/' . $this->user_data->ID;
        
        if (!file_exists($invoice_dir)) {
            wp_mkdir_p($invoice_dir);
        }
        
        // Create index.php file to prevent directory listing
        if (!file_exists($invoice_dir . '/index.php')) {
            file_put_contents($invoice_dir . '/index.php', '<?php // Silence is golden.');
        }
        
        // Generate file name
        $file_name = 'invoice-' . $this->entry_id . '-' . 
                      date('Ymd', strtotime($this->payment_details['time'])) . '.pdf';
        $file_path = $invoice_dir . '/' . $file_name;
        
        // Save the PDF
        $this->Output('F', $file_path);
        
        // Save the invoice path in entry meta
        update_post_meta($this->entry_id, 'invoice_path', $file_path);
        update_post_meta($this->entry_id, 'invoice_url', $upload_dir['baseurl'] . '/awards-invoices/' . $this->user_data->ID . '/' . $file_name);
        
        return $file_path;
    }
    
    /**
     * Create header with logo and title
     */
    private function create_header() {
        // Add logo if available
        if ($this->logo_path && file_exists($this->logo_path)) {
            $this->Image($this->logo_path, 10, 10, 50);
            $this->Ln(20);
        } else {
            // If no logo, add site name instead
            $this->SetFont('Arial', 'B', 20);
            $this->Cell(0, 10, get_bloginfo('name'), 0, 1, 'L');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 5, get_bloginfo('description'), 0, 1, 'L');
            $this->Ln(10);
        }
        
        // Add Invoice title
        $this->SetFont('Arial', 'B', 24);
        $this->Cell(0, 15, __('INVOICE', 'awards'), 0, 1, 'R');
        $this->Ln(10);
    }
    
    /**
     * Add invoice information (number, date, etc.)
     */
    private function create_invoice_info() {
        $this->SetTextColor(80, 80, 80);
        $this->SetFont('Arial', 'B', 12);
        
        // Generate invoice number
        $invoice_number = $this->entry_id . '-' . substr($this->payment_details['RefID'], 0, 8);
        
        // Add invoice number and date
        $this->Cell(95, 10, __('Invoice #:', 'awards') . ' ' . $invoice_number, 0, 0);
        $this->Cell(95, 10, __('Date:', 'awards') . ' ' . date('F j, Y', strtotime($this->payment_details['time'])), 0, 1, 'R');
        
        // Add photography contest title
        $this->SetFont('Arial', 'I', 12);
        $this->Cell(0, 10, __('Photography Contest Entry Payment', 'awards'), 0, 1, 'C');
        
        // Add horizontal line
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }
    
    /**
     * Add customer information
     */
    private function create_customer_info() {
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 7, __('Bill To:', 'awards'), 0, 1);
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, $this->user_data->first_name . ' ' . $this->user_data->last_name, 0, 1);
        $this->Cell(0, 7, $this->user_data->user_email, 0, 1);
        
        $country = get_user_meta($this->user_data->ID, 'country', true);
        if ($country) {
            $this->Cell(0, 7, $country, 0, 1);
        }
        
        $phone = get_user_meta($this->user_data->ID, 'phone', true);
        if ($phone) {
            $this->Cell(0, 7, __('Phone:', 'awards') . ' ' . $phone, 0, 1);
        }
        
        $this->Ln(10);
    }
    
    /**
     * Add entry details
     */
    private function create_entry_details() {
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 7, __('Entry Details:', 'awards'), 0, 1);
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, __('Entry Title:', 'awards') . ' ' . $this->entry_data->post_title, 0, 1);
        
        $entry_type = get_post_meta($this->entry_id, 'entrytype', true);
        $this->Cell(0, 7, __('Entry Type:', 'awards') . ' ' . ucfirst($entry_type), 0, 1);
        
        $categories = get_the_terms($this->entry_id, 'entrycat');
        if ($categories && !is_wp_error($categories)) {
            $category_names = array();
            foreach ($categories as $category) {
                $category_names[] = $category->name;
            }
            $this->Cell(0, 7, __('Categories:', 'awards') . ' ' . implode(', ', $category_names), 0, 1);
        }
        
        $this->Ln(10);
    }
    
    /**
     * Add payment summary table
     */
    private function create_payment_summary() {
        // Set up the table header
        $this->SetFillColor(240, 240, 240);
        $this->SetTextColor(50, 50, 50);
        $this->SetFont('Arial', 'B', 10);
        
        $this->Cell(95, 7, __('Description', 'awards'), 1, 0, 'L', true);
        $this->Cell(47.5, 7, __('Payment Method', 'awards'), 1, 0, 'C', true);
        $this->Cell(47.5, 7, __('Amount', 'awards'), 1, 1, 'R', true);
        
        $this->SetFont('Arial', '', 10);
        
        // Entry type
        $entry_type = get_post_meta($this->entry_id, 'entrytype', true);
        $this->Cell(95, 7, __('Entry Fee - ', 'awards') . ucfirst($entry_type), 1);
        $this->Cell(47.5, 7, ucfirst($this->payment_details['gateway']), 1, 0, 'C');
        
        // Format the amount
        $amount = $this->payment_details['Amount'];
        $this->Cell(47.5, 7, $this->currency_symbol . number_format((float)$amount, 2), 1, 1, 'R');
        
        // Check if a coupon was used
        $coupon_used = get_post_meta($this->entry_id, 'coupon_used', true);
        if ($coupon_used) {
            $this->Cell(95, 7, __('Discount (Coupon: ', 'awards') . $coupon_used['copon'] . ')', 1);
            $this->Cell(47.5, 7, '', 1, 0, 'C');
            
            if ($coupon_used['type'] == 'percent') {
                $discount_text = $coupon_used['value'] . '%';
            } else {
                $discount_text = '-' . $this->currency_symbol . number_format((float)$coupon_used['value'], 2);
            }
            
            $this->Cell(47.5, 7, $discount_text, 1, 1, 'R');
        }
        
        // Add total row
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(142.5, 7, __('Total', 'awards'), 1, 0, 'R', true);
        $this->Cell(47.5, 7, $this->currency_symbol . number_format((float)$amount, 2), 1, 1, 'R', true);
        
        $this->Ln(10);
    }
    
    /**
     * Add transaction details
     */
    private function create_transaction_details() {
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 7, __('Transaction Details:', 'awards'), 0, 1);
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, __('Transaction ID:', 'awards') . ' ' . $this->payment_details['RefID'], 0, 1);
        $this->Cell(0, 7, __('Gateway:', 'awards') . ' ' . ucfirst($this->payment_details['gateway']), 0, 1);
        $this->Cell(0, 7, __('Date:', 'awards') . ' ' . date('F j, Y H:i:s', strtotime($this->payment_details['time'])), 0, 1);
        
        $this->Ln(15);
    }
    
    /**
     * Add footer
     */
    private function create_footer() {
        // Set position at 20mm from bottom
        $this->SetY(-30);
        
        // Add horizontal line
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
        
        // Add contact information
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, get_bloginfo('name'), 0, 1, 'C');
        $this->Cell(0, 5, get_bloginfo('url'), 0, 1, 'C');
        
        // Add thank you note
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 10, __('Thank you for participating in our photography contest.', 'awards'), 0, 1, 'C');
        
        // Add page number
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, __('Page ', 'awards') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    /**
     * Override header method
     */
    public function Header() {
        // Empty - we handle this manually
    }
    
    /**
     * Override footer method
     */
    public function Footer() {
        // Empty - we handle this manually
    }
}