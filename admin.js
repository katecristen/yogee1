/**
 * Yogee Admin Dashboard
 * Primary JavaScript for the Admin Panel
 */

class YogeeAdmin {
  constructor() {
    this.currentSection = 'dashboard';
    this.initEventListeners();
    this.checkAdminSession();
  }

  /**
   * Initialize event listeners for the admin panel
   */
  initEventListeners() {
    // Navigation
    document.querySelectorAll('.nav-item').forEach(navItem => {
      navItem.addEventListener('click', (e) => {
        e.preventDefault();
        const section = navItem.getAttribute('data-section');
        if (section) {
          this.showSection(section);
        }
      });
    });

    // Logout handler
    document.getElementById('logout-btn')?.addEventListener('click', (e) => {
      e.preventDefault();
      this.logout();
    });

    // Search fields
    document.getElementById('reservation-search')?.addEventListener('input', () => this.filterReservations());
    document.getElementById('status-filter')?.addEventListener('change', () => this.filterReservations());
    document.getElementById('user-search')?.addEventListener('input', () => this.filterUsers());

    // Date inputs for reporting
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(today.getDate() - 30);
    
    const startDateInput = document.getElementById('start-date');
    const endDateInput = document.getElementById('end-date');
    
    if (startDateInput && endDateInput) {
      startDateInput.valueAsDate = thirtyDaysAgo;
      endDateInput.valueAsDate = today;
      
      startDateInput.addEventListener('change', () => this.validateDateRange());
      endDateInput.addEventListener('change', () => this.validateDateRange());
    }
  }

  /**
   * Check if admin is logged in, redirect to login if not
   */
  async checkAdminSession() {
    try {
      const response = await fetch('api/check_admin_session.php');
      const data = await response.json();
      
      if (data.error_code) {
        window.location.href = 'Login.html?error=' + data.error_code;
        return;
      }
      
      // Set admin info
      if (data.name) {
        document.getElementById('admin-name').textContent = data.name;
        
        // Set avatar letter using first letter of name
        const avatarEl = document.querySelector('.admin-avatar');
        if (avatarEl) {
          avatarEl.textContent = data.name.charAt(0).toUpperCase();
        }
      }
      
      // Load the dashboard data
      this.loadDashboardData();
    } catch (error) {
      console.error('Session check error:', error);
    }
  }

  /**
   * Switch between dashboard sections
   */
  showSection(sectionName) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
      section.classList.remove('active');
    });

    // Remove active class from nav items
    document.querySelectorAll('.nav-item').forEach(item => {
      item.classList.remove('active');
    });

    // Show selected section
    document.getElementById(sectionName).classList.add('active');
    document.querySelector(`[data-section="${sectionName}"]`).classList.add('active');

    // Update page title
    const titles = {
      dashboard: 'Dashboard',
      reservations: 'Reservations',
      packages: 'Packages',
      users: 'Users',
      inventory: 'Inventory', 
      reports: 'Reports'
    };
    document.getElementById('page-title').textContent = titles[sectionName];
    
    // Load section data
    switch (sectionName) {
      case 'dashboard':
        this.loadDashboardData();
        break;
      case 'reservations':
        this.loadReservations();
        break;
      case 'packages':
        this.loadPackages();
        break;
      case 'users':
        this.loadUsers();
        break;
      case 'inventory':
        this.loadInventory();
        break;
      case 'reports':
        this.generateReport();
        break;
    }
    
    // Update URL hash without refreshing
    window.history.pushState(null, '', `#${sectionName}`);
    this.currentSection = sectionName;
  }

  /**
   * Load dashboard data from APIs
   */
  async loadDashboardData() {
    try {
      // Load dashboard stats
      const statsRes = await fetch('api/admin_stats.php');
      const stats = await statsRes.json();
      
      document.getElementById('total-reservations').textContent = stats.total_reservations || '0';
      document.getElementById('confirmed-reservations').textContent = stats.confirmed_reservations || '0';
      document.getElementById('pending-reservations').textContent = stats.pending_reservations || '0';
      document.getElementById('total-users').textContent = stats.total_users || '0';
      
      // Load recent reservations
      this.loadRecentReservations();
    } catch (error) {
      console.error('Error loading dashboard data:', error);
    }
  }

  /**
   * Load recent reservations for the dashboard
   */
  async loadRecentReservations() {
    const tbody = document.querySelector('#recent-reservations-table tbody');
    
    try {
      const res = await fetch('api/admin_reservations.php?recent=1');
      const data = await res.json();
      
      tbody.innerHTML = '';
      
      if (Array.isArray(data) && data.length > 0) {
        data.forEach(reservation => {
          const row = document.createElement('tr');
          row.innerHTML = `
            <td>${this.formatDate(reservation.date)}</td>
            <td>${this.escapeHtml(reservation.customer_name)}</td>
            <td>${this.escapeHtml(reservation.package_name)}</td>
            <td><span class="status-badge status-${reservation.status}">${this.capitalizeFirst(reservation.status)}</span></td>
            <td>₱${this.formatNumber(reservation.total_amount)}</td>
            <td><button class="btn-primary btn-small" onclick="admin.viewReservation(${reservation.id})">View</button></td>
          `;
          tbody.appendChild(row);
        });
      } else {
        tbody.innerHTML = '<tr><td colspan="6" class="no-data">No recent reservations found.</td></tr>';
      }
    } catch (error) {
      console.error('Error loading recent reservations:', error);
      tbody.innerHTML = '<tr><td colspan="6" class="no-data">Error loading reservations.</td></tr>';
    }
  }

  /**
   * Load all reservations
   */
  async loadReservations() {
    const tbody = document.querySelector('#reservations-table tbody');
    
    try {
      const res = await fetch('api/admin_reservations.php');
      const data = await res.json();
      
      tbody.innerHTML = '';
      
      if (Array.isArray(data) && data.length > 0) {
        data.forEach(reservation => {
          const row = document.createElement('tr');
          row.dataset.status = reservation.status;
          row.dataset.search = `${reservation.id} ${reservation.customer_name} ${reservation.email} ${reservation.package_name}`.toLowerCase();
          
          row.innerHTML = `
            <td>${reservation.id}</td>
            <td>${this.escapeHtml(reservation.customer_name)}</td>
            <td>${this.escapeHtml(reservation.email)}</td>
            <td>${this.escapeHtml(reservation.package_name)}</td>
            <td>${this.formatDateTime(reservation.date)}</td>
            <td>${reservation.guests}</td>
            <td>₱${this.formatNumber(reservation.total_amount)}</td>
            <td><span class="status-badge status-${reservation.status}">${this.capitalizeFirst(reservation.status)}</span></td>
            <td>
              <button class="btn-primary btn-small" onclick="admin.viewReservation(${reservation.id})">View</button>
              <button class="btn-secondary btn-small" onclick="admin.editReservation(${reservation.id})">Edit</button>
            </td>
          `;
          tbody.appendChild(row);
        });
      } else {
        tbody.innerHTML = '<tr><td colspan="9" class="no-data">No reservations found.</td></tr>';
      }
    } catch (error) {
      console.error('Error loading reservations:', error);
      tbody.innerHTML = '<tr><td colspan="9" class="no-data">Error loading reservations.</td></tr>';
    }
  }

  /**
   * Filter reservations based on search and status
   */
  filterReservations() {
    const searchTerm = document.getElementById('reservation-search').value.toLowerCase();
    const statusFilter = document.getElementById('status-filter').value;
    const rows = document.querySelectorAll('#reservations-table tbody tr');
    
    let visibleCount = 0;
    
    rows.forEach(row => {
      const searchText = row.dataset.search || '';
      const status = row.dataset.status || '';
      
      const statusMatch = statusFilter === '' || status === statusFilter;
      const searchMatch = searchText.includes(searchTerm);
      
      if (statusMatch && searchMatch) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });
    
    // Show no results message if needed
    const noResultsRow = document.querySelector('#reservations-table .no-results');
    
    if (visibleCount === 0 && !noResultsRow) {
      const tbody = document.querySelector('#reservations-table tbody');
      const messageRow = document.createElement('tr');
      messageRow.className = 'no-results';
      messageRow.innerHTML = `<td colspan="9" class="no-data">No reservations found matching your filters.</td>`;
      tbody.appendChild(messageRow);
    } else if (visibleCount > 0 && noResultsRow) {
      noResultsRow.remove();
    }
  }

  /**
   * Load all packages
   */
  async loadPackages() {
    const grid = document.getElementById('packages-grid');
    
    try {
      const res = await fetch('api/admin_packages.php');
      const data = await res.json();
      
      grid.innerHTML = '';
      
      if (Array.isArray(data) && data.length > 0) {
        data.forEach(pkg => {
          const card = document.createElement('div');
          card.className = 'package-card';
          card.innerHTML = `
            <div class="package-header">
              <h3>${this.escapeHtml(pkg.name)}</h3>
              <div class="package-price">₱${this.formatNumber(pkg.price)}</div>
            </div>
            <div class="package-body">
              <div class="package-description">${this.escapeHtml(pkg.description)}</div>
              <div class="package-meta">
                <span>Max Guests: ${pkg.max_guests}</span>
              </div>
              <div class="package-actions">
                <button class="btn-primary btn-small" onclick="admin.editPackage(${pkg.id})">Edit</button>
                <button class="btn-warning btn-small" onclick="admin.deletePackage(${pkg.id}, '${this.escapeHtml(pkg.name)}')">Delete</button>
              </div>
            </div>
          `;
          grid.appendChild(card);
        });
      } else {
        grid.innerHTML = '<div class="no-data-message">No packages found.</div>';
      }
    } catch (error) {
      console.error('Error loading packages:', error);
      grid.innerHTML = '<div class="no-data-message">Error loading packages.</div>';
    }
  }

  /**
   * Load all users
   */
  async loadUsers() {
    const tbody = document.querySelector('#users-table tbody');
    
    try {
      const res = await fetch('api/admin_users.php');
      const data = await res.json();
      
      tbody.innerHTML = '';
      
      if (Array.isArray(data) && data.length > 0) {
        data.forEach(user => {
          const row = document.createElement('tr');
          row.dataset.search = `${user.id} ${user.first_name} ${user.last_name} ${user.email}`.toLowerCase();
          
          row.innerHTML = `
            <td>${user.id}</td>
            <td>${this.escapeHtml(user.first_name)} ${this.escapeHtml(user.last_name)}</td>
            <td>${this.escapeHtml(user.email)}</td>
            <td>${user.phone ? this.escapeHtml(user.phone) : 'N/A'}</td>
            <td>${this.formatDate(user.created_at)}</td>
            <td>
              <button class="btn-primary btn-small" onclick="admin.viewUser(${user.id})">View</button>
              <button class="btn-warning btn-small" onclick="admin.deleteUser(${user.user_id}, '${this.escapeHtml(user.email)}')">Delete</button>
            </td>
          `;
          tbody.appendChild(row);
        });
      } else {
        tbody.innerHTML = '<tr><td colspan="6" class="no-data">No users found.</td></tr>';
      }
    } catch (error) {
      console.error('Error loading users:', error);
      tbody.innerHTML = '<tr><td colspan="6" class="no-data">Error loading users.</td></tr>';
    }
  }

  /**
   * Filter users based on search
   */
  filterUsers() {
    const searchTerm = document.getElementById('user-search').value.toLowerCase();
    const rows = document.querySelectorAll('#users-table tbody tr');
    
    let visibleCount = 0;
    
    rows.forEach(row => {
      const searchText = row.dataset.search || '';
      
      if (searchText.includes(searchTerm)) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });
    
    // Show no results message if needed
    const noResultsRow = document.querySelector('#users-table .no-results');
    
    if (visibleCount === 0 && !noResultsRow) {
      const tbody = document.querySelector('#users-table tbody');
      const messageRow = document.createElement('tr');
      messageRow.className = 'no-results';
      messageRow.innerHTML = `<td colspan="6" class="no-data">No users found matching your search.</td>`;
      tbody.appendChild(messageRow);
    } else if (visibleCount > 0 && noResultsRow) {
      noResultsRow.remove();
    }
  }

  /**
   * Load inventory data
   */
  async loadInventory() {
    const content = document.getElementById('inventory-content');
    
    try {
      const res = await fetch('api/admin_inventory.php');
      let data = [];
      
      try {
        data = await res.json();
      } catch (e) {
        // If API doesn't exist yet, use sample data
        data = [
          { id: 1, name: 'Yogurt Cups (8oz)', quantity: 150, category: 'Containers', status: 'In Stock' },
          { id: 2, name: 'Plastic Spoons', quantity: 45, category: 'Utensils', status: 'Low Stock' },
          { id: 3, name: 'Greek Yogurt Base', quantity: 25, category: 'Ingredients', status: 'In Stock' },
          { id: 4, name: 'Fresh Berries', quantity: 12, category: 'Toppings', status: 'Low Stock' },
          { id: 5, name: 'Granola Mix', quantity: 8, category: 'Toppings', status: 'In Stock' }
        ];
      }
      
      // Count items by status
      const inStock = data.filter(item => item.status === 'In Stock').length;
      const lowStock = data.filter(item => item.status === 'Low Stock').length;
      
      let html = `
        <div class="inventory-stats">
          <div class="stat-card"><h3>${data.length}</h3><p>Total Items</p></div>
          <div class="stat-card"><h3>${inStock}</h3><p>In Stock</p></div>
          <div class="stat-card"><h3>${lowStock}</h3><p>Low Stock</p></div>
        </div>
        <div class="table-container">
          <table>
            <thead>
              <tr><th>ID</th><th>Item Name</th><th>Category</th><th>Quantity</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
      `;
      
      if (data.length > 0) {
        data.forEach(item => {
          const statusClass = item.status === 'Low Stock' ? 'status-warning' : 'status-success';
          html += `
            <tr>
              <td>${item.id}</td>
              <td>${this.escapeHtml(item.name)}</td>
              <td>${this.escapeHtml(item.category)}</td>
              <td>${item.quantity}</td>
              <td><span class="status-badge ${statusClass}">${item.status}</span></td>
              <td>
                <button class="btn-primary btn-small" onclick="admin.editInventoryItem(${item.id})">Edit</button>
                <button class="btn-warning btn-small" onclick="admin.deleteInventoryItem(${item.id}, '${this.escapeHtml(item.name)}')">Delete</button>
              </td>
            </tr>
          `;
        });
      } else {
        html += '<tr><td colspan="6" class="no-data">No inventory items found.</td></tr>';
      }
      
      html += '</tbody></table></div>';
      content.innerHTML = html;
    } catch (error) {
      console.error('Error loading inventory:', error);
      content.innerHTML = '<div class="error-message">Error loading inventory data.</div>';
    }
  }

  /**
   * Generate sales report based on date range
   */
  async generateReport() {
    const startDate = document.getElementById('start-date').value;
    const endDate = document.getElementById('end-date').value;
    const content = document.getElementById('reports-content');
    
    if (!startDate || !endDate) {
      content.innerHTML = '<div class="error-message">Please select both start and end dates.</div>';
      return;
    }
    
    try {
      // Try to get real data from API
      let reportData = null;
      
      try {
        const res = await fetch(`api/admin_reports.php?start=${startDate}&end=${endDate}`);
        reportData = await res.json();
      } catch (e) {
        // If API doesn't exist yet, use sample data
        reportData = {
          total_revenue: 125000,
          total_reservations: 45,
          avg_value: 2778,
          by_package: [
            { name: 'Premium Package', count: 25, revenue: 75000 },
            { name: 'Basic Package', count: 12, revenue: 30000 },
            { name: 'Corporate Package', count: 8, revenue: 20000 }
          ],
          by_date: [
            { date: '2025-05-10', count: 3, revenue: 7500 },
            { date: '2025-05-11', count: 5, revenue: 12500 },
            { date: '2025-05-12', count: 4, revenue: 10000 },
            { date: '2025-05-13', count: 7, revenue: 17500 },
            { date: '2025-05-14', count: 2, revenue: 5000 },
          ]
        };
      }
      
      const dateRangeStr = `${this.formatDate(startDate)} to ${this.formatDate(endDate)}`;
      
      let html = `
        <div class="report-summary">
          <h3>Revenue Report (${dateRangeStr})</h3>
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-icon">💰</div>
              <div class="stat-info">
                <h3>₱${this.formatNumber(reportData.total_revenue)}</h3>
                <p>Total Revenue</p>
              </div>
            </div>
            <div class="stat-card">
              <div class="stat-icon">📅</div>
              <div class="stat-info">
                <h3>${reportData.total_reservations}</h3>
                <p>Total Reservations</p>
              </div>
            </div>
            <div class="stat-card">
              <div class="stat-icon">📊</div>
              <div class="stat-info">
                <h3>₱${this.formatNumber(reportData.avg_value)}</h3>
                <p>Avg. Reservation Value</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="report-tables">
          <div class="report-section">
            <h3>Revenue by Package</h3>
            <table>
              <thead><tr><th>Package</th><th>Reservations</th><th>Revenue</th></tr></thead>
              <tbody>
      `;
      
      reportData.by_package.forEach(pkg => {
        html += `
          <tr>
            <td>${this.escapeHtml(pkg.name)}</td>
            <td>${pkg.count}</td>
            <td>₱${this.formatNumber(pkg.revenue)}</td>
          </tr>
        `;
      });
      
      html += `
              </tbody>
            </table>
          </div>
          
          <div class="report-section">
            <h3>Daily Revenue</h3>
            <table>
              <thead><tr><th>Date</th><th>Reservations</th><th>Revenue</th></tr></thead>
              <tbody>
      `;
      
      reportData.by_date.forEach(day => {
        html += `
          <tr>
            <td>${this.formatDate(day.date)}</td>
            <td>${day.count}</td>
            <td>₱${this.formatNumber(day.revenue)}</td>
          </tr>
        `;
      });
      
      html += `
              </tbody>
            </table>
          </div>
        </div>
        
        <div class="report-actions">
          <button class="btn-primary" onclick="admin.exportReport('pdf')">Export PDF</button>
          <button class="btn-secondary" onclick="admin.exportReport('csv')">Export CSV</button>
        </div>
      `;
      
      content.innerHTML = html;
    } catch (error) {
      console.error('Error generating report:', error);
      content.innerHTML = '<div class="error-message">Error generating report.</div>';
    }
  }

  /**
   * View reservation details
   */
  async viewReservation(reservationId) {
    try {
      let reservation = null;
      
      // Try to get real data from API
      try {
        const res = await fetch(`api/admin_reservations.php?id=${reservationId}`);
        reservation = await res.json();
      } catch (e) {
        // If API doesn't return data yet, use sample data
        reservation = {
          id: reservationId,
          customer_name: 'Maria Santos',
          email: 'maria@email.com',
          phone: '09123456789',
          package_name: 'Premium Yogurt Party Package',
          date: '2025-06-15 10:00:00',
          guests: 8,
          total_amount: 2500,
          status: 'confirmed',
          special_requests: 'Please include extra strawberry yogurt. Some guests have nut allergies.',
          payment_status: 'Paid',
          notes: 'Birthday party for 8-year-old daughter. Requested colorful decorations.',
          created_at: '2025-05-20 14:30:00'
        };
      }
      
      // Populate the reservation details modal
      const detailsDiv = document.getElementById('reservation-details');
      detailsDiv.innerHTML = `
        <div class="reservation-info">
          <div class="info-section">
            <h4>Customer Information</h4>
            <div class="info-grid">
              <div class="info-item">
                <label>Name:</label>
                <span>${this.escapeHtml(reservation.customer_name)}</span>
              </div>
              <div class="info-item">
                <label>Email:</label>
                <span>${this.escapeHtml(reservation.email)}</span>
              </div>
              <div class="info-item">
                <label>Phone:</label>
                <span>${this.escapeHtml(reservation.phone || 'N/A')}</span>
              </div>
            </div>
          </div>
  
          <div class="info-section">
            <h4>Reservation Details</h4>
            <div class="info-grid">
              <div class="info-item">
                <label>Package:</label>
                <span>${this.escapeHtml(reservation.package_name)}</span>
              </div>
              <div class="info-item">
                <label>Date:</label>
                <span>${this.formatDate(reservation.date)}</span>
              </div>
              <div class="info-item">
                <label>Time:</label>
                <span>${this.formatTime(reservation.date)}</span>
              </div>
              <div class="info-item">
                <label>Number of Guests:</label>
                <span>${reservation.guests}</span>
              </div>
              <div class="info-item">
                <label>Total Amount:</label>
                <span>₱${this.formatNumber(reservation.total_amount)}</span>
              </div>
              <div class="info-item">
                <label>Status:</label>
                <span class="status-badge status-${reservation.status}">${this.capitalizeFirst(reservation.status)}</span>
              </div>
              <div class="info-item">
                <label>Payment Status:</label>
                <span class="payment-status ${(reservation.payment_status || '').toLowerCase()}">${reservation.payment_status || 'Unknown'}</span>
              </div>
            </div>
          </div>
  
          <div class="info-section">
            <h4>Special Requests</h4>
            <div class="special-requests">
              <p>${this.escapeHtml(reservation.special_requests || 'None')}</p>
            </div>
          </div>
  
          <div class="info-section">
            <h4>Admin Notes</h4>
            <div class="admin-notes">
              <p>${this.escapeHtml(reservation.notes || 'No notes')}</p>
            </div>
          </div>
  
          <div class="info-section">
            <h4>Update Status</h4>
            <div class="status-update">
              <select id="new-status" class="form-control">
                <option value="pending" ${reservation.status === 'pending' ? 'selected' : ''}>Pending</option>
                <option value="confirmed" ${reservation.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                <option value="completed" ${reservation.status === 'completed' ? 'selected' : ''}>Completed</option>
                <option value="cancelled" ${reservation.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
              </select>
            </div>
          </div>
        </div>
      `;
  
      // Store the current reservation ID for the update function
      window.currentReservationId = reservationId;
  
      // Show the modal
      document.getElementById('reservation-modal').classList.add('show');
    } catch (error) {
      console.error('Error viewing reservation:', error);
      alert('Error loading reservation details.');
    }
  }

  /**
   * Update reservation status
   */
  async updateReservationStatus() {
    const newStatus = document.getElementById('new-status').value;
    const reservationId = window.currentReservationId;
    
    if (!newStatus || !reservationId) {
      alert('Please select a status');
      return;
    }
  
    try {
      // Make API call to update status
      const response = await fetch('api/admin_reservations.php', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          reservation_id: reservationId,
          status: newStatus
        })
      });
      
      const result = await response.json();
      
      if (result.success) {
        alert(`Reservation #${reservationId} status updated to: ${newStatus.toUpperCase()}`);
        
        // Close the modal
        this.closeModal('reservation-modal');
        
        // Refresh current section data
        if (this.currentSection === 'dashboard') {
          this.loadDashboardData();
        } else if (this.currentSection === 'reservations') {
          this.loadReservations();
        }
      } else {
        throw new Error(result.message || 'Failed to update status');
      }
    } catch (error) {
      console.error('Error updating reservation status:', error);
      alert(`Error updating status: ${error.message || 'Unknown error'}`);
    }
  }

  /**
   * Delete a user after confirmation
   */
  async deleteUser(userId, email) {
    if (!confirm(`Are you sure you want to delete user ${email}? This action cannot be undone.`)) {
      return;
    }
    
    try {
      const response = await fetch('api/admin_users.php', {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          user_id: userId
        })
      });
      
      const result = await response.json();
      
      if (result.success) {
        alert(`User ${email} has been deleted.`);
        this.loadUsers();
      } else {
        throw new Error(result.message || 'Failed to delete user');
      }
    } catch (error) {
      console.error('Error deleting user:', error);
      alert(`Error deleting user: ${error.message || 'Unknown error'}`);
    }
  }

  /**
   * Edit package
   */
  editPackage(packageId) {
    alert(`Edit package functionality will be implemented soon for package ID: ${packageId}`);
  }

  /**
   * Delete a package after confirmation
   */
  deletePackage(packageId, packageName) {
    if (!confirm(`Are you sure you want to delete the package "${packageName}"? This action cannot be undone.`)) {
      return;
    }
    
    alert(`Delete package functionality will be implemented soon for package ID: ${packageId}`);
  }

  /**
   * Edit an inventory item
   */
  editInventoryItem(itemId) {
    alert(`Edit inventory item functionality will be implemented soon for item ID: ${itemId}`);
  }

  /**
   * Delete an inventory item after confirmation
   */
  deleteInventoryItem(itemId, itemName) {
    if (!confirm(`Are you sure you want to delete "${itemName}" from inventory? This action cannot be undone.`)) {
      return;
    }
    
    alert(`Delete inventory item functionality will be implemented soon for item ID: ${itemId}`);
  }

  /**
   * Export report in various formats
   */
  exportReport(format) {
    const startDate = document.getElementById('start-date').value;
    const endDate = document.getElementById('end-date').value;
    
    if (format === 'pdf') {
      alert(`Generating PDF report for ${startDate} to ${endDate}. This will be implemented soon.`);
    } else if (format === 'csv') {
      alert(`Generating CSV report for ${startDate} to ${endDate}. This will be implemented soon.`);
    }
  }

  /**
   * Validate date range for reports
   */
  validateDateRange() {
    const startDate = document.getElementById('start-date').valueAsDate;
    const endDate = document.getElementById('end-date').valueAsDate;
    
    if (startDate && endDate && startDate > endDate) {
      alert('Start date cannot be after end date.');
      document.getElementById('start-date').valueAsDate = endDate;
    }
  }

  /**
   * Show Add Package modal
   */
  showAddPackageModal() {
    // Clear form
    document.getElementById('package-form').reset();
    
    // Show modal
    document.getElementById('package-modal').classList.add('show');
  }

  /**
   * Show Add Inventory Item modal
   */
  showAddItemModal() {
    // Clear form
    document.getElementById('inventory-form').reset();
    
    // Show modal
    document.getElementById('inventory-modal').classList.add('show');
  }

  /**
   * Close modal
   */
  closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
  }

  /**
   * Save package from modal
   */
  async savePackage() {
    const packageName = document.getElementById('package-name').value;
    const packageDesc = document.getElementById('package-description').value;
    const packagePrice = document.getElementById('package-price').value;
    const packageMaxGuests = document.getElementById('package-max-guests').value;
    
    if (!packageName || !packagePrice || !packageMaxGuests) {
      alert('Please fill in all required fields.');
      return;
    }
    
    try {
      const response = await fetch('api/admin_packages.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          name: packageName,
          description: packageDesc,
          price: packagePrice,
          max_guests: packageMaxGuests
        })
      });
      
      const result = await response.json();
      
      if (result.success) {
        alert('Package saved successfully.');
        this.closeModal('package-modal');
        this.loadPackages();
      } else {
        throw new Error(result.message || 'Failed to save package');
      }
    } catch (error) {
      console.error('Error saving package:', error);
      alert(`Error saving package: ${error.message || 'Unknown error'}`);
    }
  }

  /**
   * Save inventory item from modal
   */
  async saveInventoryItem() {
    const itemName = document.getElementById('item-name').value;
    const itemCategory = document.getElementById('item-category').value;
    const itemQuantity = document.getElementById('item-quantity').value;
    const itemMinStock = document.getElementById('item-min-stock').value;
    
    if (!itemName || !itemCategory || !itemQuantity) {
      alert('Please fill in all required fields.');
      return;
    }
    
    try {
      // Demonstrate with sample implementation (no actual API yet)
      alert('Inventory item saved (demo)');
      this.closeModal('inventory-modal');
      this.loadInventory();
    } catch (error) {
      console.error('Error saving inventory item:', error);
      alert(`Error saving inventory item: ${error.message || 'Unknown error'}`);
    }
  }

  /**
   * Log out the admin user
   */
  async logout() {
    if (confirm('Are you sure you want to logout?')) {
      try {
        const response = await fetch('api/admin_logout.php');
        const data = await response.json();
        
        if (data.success) {
          window.location.href = data.redirect || 'Login.html';
        } else {
          throw new Error(data.message || 'Logout failed');
        }
      } catch (error) {
        console.error('Logout error:', error);
        window.location.href = 'Login.html'; // Fallback to login page anyway
      }
    }
  }

  // UTILITY FUNCTIONS

  /**
   * Format date to local date string
   */
  formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleDateString();
  }
  
  /**
   * Format date and time
   */
  formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleString();
  }
  
  /**
   * Format time only
   */
  formatTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '';
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
  
  /**
   * Format number with commas
   */
  formatNumber(num) {
    return Number(num).toLocaleString();
  }
  
  /**
   * Capitalize first letter
   */
  capitalizeFirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
  }
  
  /**
   * Escape HTML to prevent XSS
   */
  escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
}

// Create global admin instance
let admin;
document.addEventListener('DOMContentLoaded', function() {
  admin = new YogeeAdmin();
  console.log('✅ Yogee Admin Dashboard loaded!');
  
  // Set up global function references
  window.showAddPackageModal = () => admin.showAddPackageModal();
  window.showAddItemModal = () => admin.showAddItemModal();
  window.closeModal = (id) => admin.closeModal(id);
  window.savePackage = () => admin.savePackage();
  window.saveInventoryItem = () => admin.saveInventoryItem();
  window.generateReport = () => admin.generateReport();
  window.viewReservation = (id) => admin.viewReservation(id);
  window.updateReservationStatus = () => admin.updateReservationStatus();
});
