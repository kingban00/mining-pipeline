import { Component, inject, OnInit, OnDestroy, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CompanyService } from '../../services/company.service';
import { Company } from '../../models/company.model';
import { DatePipe, CommonModule } from '@angular/common';
import { interval, Subscription, Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [FormsModule, DatePipe, CommonModule],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.scss'
})
export class DashboardComponent implements OnInit, OnDestroy {
  private companyService = inject(CompanyService);

  // Inputs & Search
  companiesInput = signal<string>('');
  searchQuery = signal<string>('');
  private searchSubject = new Subject<string>();

  // Data State
  companies = signal<Company[]>([]);
  totalCompaniesCount = signal<number>(0);
  selectedCompany = signal<Company | null>(null);

  // Status & Feedback
  isListLoading = signal<boolean>(false);
  isDetailLoading = signal<boolean>(false);
  isProcessing = signal<boolean>(false);
  isPipelineActive = signal<boolean>(false);
  notification = signal<{ message: string; type: 'success' | 'error' | 'info' } | null>(null);
  viewingCompanyId = signal<string | null>(null);

  // Pagination
  currentPage = signal<number>(1);
  totalPages = signal<number>(1);
  pageSize = signal<number>(10);

  private pollSubscription?: Subscription;
  private searchSubscription?: Subscription;

  ngOnInit(): void {
    this.loadCompanies();
    this.checkInitialQueueStatus();

    // Optimize search with debounce (400ms) to prevent API spamming
    this.searchSubscription = this.searchSubject
      .pipe(debounceTime(400), distinctUntilChanged())
      .subscribe(() => {
        this.loadCompanies(1);
      });
  }

  ngOnDestroy(): void {
    this.stopPolling();
    this.searchSubscription?.unsubscribe();
  }

  onSearchChange(query: string): void {
    this.searchQuery.set(query);
    this.searchSubject.next(query);
  }

  showNotification(message: string, type: 'success' | 'error' | 'info' = 'info'): void {
    this.notification.set({ message, type });
    setTimeout(() => this.notification.set(null), 5000);
  }

  /**
   * Check if there are existing background jobs on load.
   */
  private checkInitialQueueStatus(): void {
    this.companyService.getQueueStatus().subscribe({
      next: (status) => {
        if (status.is_processing) {
          this.startPolling();
        }
      }
    });
  }

  loadCompanies(page: number = 1): void {
    // Only show full-screen list skeleton if not polling
    if (!this.isPipelineActive()) {
      this.isListLoading.set(true);
    }

    this.currentPage.set(page);

    this.companyService.getCompanies(this.currentPage(), this.searchQuery()).subscribe({
      next: (response) => {
        this.companies.set(response.data);
        this.totalCompaniesCount.set(response.total);
        this.totalPages.set(Math.ceil(response.total / response.per_page));
        this.pageSize.set(response.per_page);
        this.isListLoading.set(false);
      },
      error: (err) => {
        console.error('API Error:', err);
        this.showNotification('Error loading data from the server.', 'error');
        this.isListLoading.set(false);
      }
    });
  }

  processCompanies(): void {
    const input = this.companiesInput().trim();
    if (!input) return;

    this.isProcessing.set(true);

    this.companyService.processCompanies(input).subscribe({
      next: (res) => {
        this.isProcessing.set(false);
        this.companiesInput.set('');
        this.showNotification(`${res.message} (${res.companies_queued} queued)`, 'success');
        this.startPolling();
      },
      error: (err) => {
        this.isProcessing.set(false);
        this.showNotification(err.error?.message || 'Failed to start processing.', 'error');
      }
    });
  }

  startPolling(): void {
    if (this.pollSubscription) return; // Already polling

    this.isPipelineActive.set(true);

    // Poll every 15 seconds for status updates
    this.pollSubscription = interval(15000).subscribe(() => {
      this.companyService.getQueueStatus().subscribe({
        next: (status) => {
          if (!status.is_processing) {
            this.stopPolling();
            this.showNotification('AI analysis complete. Intelligence Lake updated.', 'success');
          }
          // Synchronize data table regardless of status
          this.loadCompanies(this.currentPage());
        },
        error: () => {
          this.loadCompanies(this.currentPage()); // Fallback
        }
      });
    });
  }

  stopPolling(): void {
    this.isPipelineActive.set(false);
    this.pollSubscription?.unsubscribe();
    this.pollSubscription = undefined;
  }

  viewDetails(id: string): void {
    // Prevent multiple concurrent clicks for details
    if (this.isDetailLoading()) return;

    this.isDetailLoading.set(true);
    this.viewingCompanyId.set(id);

    this.companyService.getCompanyById(id).subscribe({
      next: (company) => {
        this.selectedCompany.set(company);
        this.isDetailLoading.set(false);
        this.viewingCompanyId.set(null);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      },
      error: (err) => {
        console.error('API Error:', err);
        this.showNotification('Could not load intelligence details.', 'error');
        this.isDetailLoading.set(false);
        this.viewingCompanyId.set(null);
      }
    });
  }

  closeDetails(): void {
    this.selectedCompany.set(null);
    this.loadCompanies(this.currentPage());
  }
}