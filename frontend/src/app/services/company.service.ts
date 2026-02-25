import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Company, PaginatedResponse } from '../models/company.model';
import { environment } from '../../environments/environment.development';

@Injectable({
    providedIn: 'root'
})
export class CompanyService {
    private http = inject(HttpClient);

    private apiUrl = `${environment.apiUrl}/companies`;

    processCompanies(companiesString: string): Observable<{ message: string; companies_queued: number }> {
        return this.http.post<{ message: string; companies_queued: number }>(`${this.apiUrl}/process`, {
            companies: companiesString
        });
    }

    getCompanies(page: number = 1, search: string = ''): Observable<PaginatedResponse<Company>> {
        let params = new HttpParams().set('page', page.toString());

        if (search.trim() !== '') {
            params = params.set('search', search);
        }

        return this.http.get<PaginatedResponse<Company>>(this.apiUrl, { params });
    }

    getCompanyById(id: string): Observable<Company> {
        return this.http.get<Company>(`${this.apiUrl}/${id}`);
    }

    getQueueStatus(): Observable<{ is_processing: boolean; pending_jobs: number }> {
        return this.http.get<{ is_processing: boolean; pending_jobs: number }>(`${this.apiUrl}/status`);
    }
}