// src/app/services/company.service.spec.ts

import { TestBed } from '@angular/core/testing';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';
import { CompanyService } from './company.service';
import { Company, PaginatedResponse } from '../models/company.model';
import { environment } from '../../environments/environment.development';
import { describe, it, expect, beforeEach, afterEach } from 'vitest';

describe('CompanyService', () => {
    let service: CompanyService;
    let httpMock: HttpTestingController;

    const apiUrl = `${environment.apiUrl}/companies`;

    beforeEach(() => {
        TestBed.configureTestingModule({
            providers: [
                CompanyService,
                provideHttpClient(),
                provideHttpClientTesting() // Modern Angular standard for mocking HTTP requests
            ]
        });

        service = TestBed.inject(CompanyService);
        httpMock = TestBed.inject(HttpTestingController);
    });

    afterEach(() => {
        // Assert that there are no outstanding requests after each test
        httpMock.verify();
    });

    it('should be created', () => {
        expect(service).toBeTruthy();
    });

    it('should send a comma-delimited string to process (POST)', () => {
        // Arrange: Prepare dummy response and input
        const mockResponse = { message: 'Processing started', companies_queued: 2 };
        const dummyInput = 'BHP, Vale';

        // Act: Call the service method
        service.processCompanies(dummyInput).subscribe(response => {
            // Assert: Verify the response matches our mock
            expect(response.companies_queued).toBe(2);
            expect(response.message).toBe('Processing started');
        });

        // Assert: Intercept the request and verify method, URL, and payload
        const req = httpMock.expectOne(`${apiUrl}/process`);
        expect(req.request.method).toBe('POST');
        expect(req.request.body).toEqual({ companies: dummyInput });

        // Resolve the request by flushing the mock data
        req.flush(mockResponse);
    });

    it('should fetch paginated companies with search parameters (GET)', () => {
        // Arrange
        const mockResponse: PaginatedResponse<Company> = {
            current_page: 2,
            data: [{ id: '123', name: 'BHP', created_at: '2026-02-24', status: 'completed' }],
            first_page_url: '', last_page: 2, last_page_url: '', next_page_url: null,
            path: '', per_page: 10, prev_page_url: null, to: 11, total: 11
        };

        // Act
        service.getCompanies(2, 'Mining').subscribe(response => {
            // Assert
            expect(response.data.length).toBe(1);
            expect(response.data[0].name).toBe('BHP');
        });

        // Assert: Verify the URL includes the correct query parameters dynamically
        const req = httpMock.expectOne(request =>
            request.url === apiUrl &&
            request.params.get('page') === '2' &&
            request.params.get('search') === 'Mining'
        );
        expect(req.request.method).toBe('GET');

        req.flush(mockResponse);
    });

    it('should fetch a specific company by ID (GET)', () => {
        // Arrange
        const mockCompany: Company = {
            id: 'uuid-123',
            name: 'Evolution',
            status: 'completed',
            created_at: '2026-02-24',
            executives: [],
            assets: []
        };

        // Act
        service.getCompanyById('uuid-123').subscribe(company => {
            // Assert
            expect(company.name).toBe('Evolution');
        });

        // Assert: Verify the URL mounts the UUID correctly
        const req = httpMock.expectOne(`${apiUrl}/uuid-123`);
        expect(req.request.method).toBe('GET');

        req.flush(mockCompany);
    });
});