import { ComponentFixture, TestBed } from '@angular/core/testing';
import { DashboardComponent } from './dashboard.component';
import { CompanyService } from '../../services/company.service';
import { of } from 'rxjs';
import { HttpClientTestingModule } from '@angular/common/http/testing';

describe('DashboardComponent', () => {
  let component: DashboardComponent;
  let fixture: ComponentFixture<DashboardComponent>;
  let companyService: CompanyService;

  const mockCompanies = {
    data: [
      { id: '1', name: 'Test Mining Co', created_at: '2024-01-01', status: 'completed' as const },
      {
        id: '2',
        name: 'Another Mine',
        created_at: '2024-01-02',
        status: 'completed' as const,
        executives: [{ id: '1', name: 'John Doe', expertise: ['Mining'], technical_summary: ['Exp 1', 'Exp 2', 'Exp 3'] }],
        assets: []
      }
    ],
    total: 2,
    per_page: 10,
    current_page: 1,
    last_page: 1
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DashboardComponent, HttpClientTestingModule],
      providers: [CompanyService]
    }).compileComponents();

    fixture = TestBed.createComponent(DashboardComponent);
    component = fixture.componentInstance;
    companyService = TestBed.inject(CompanyService);

    spyOn(companyService, 'getCompanies').and.returnValue(of(mockCompanies as any));
    spyOn(companyService, 'getQueueStatus').and.returnValue(of({ is_processing: false, pending_jobs: 0 }));

    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should load companies on init', () => {
    expect(companyService.getCompanies).toHaveBeenCalled();
    expect(component.companies().length).toBe(2);
  });
});