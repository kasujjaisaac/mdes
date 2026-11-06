<?php

return [
    'name' => 'Masaka Diocesan Education Secretariat Portal',
    'short_name' => 'MDES',

    'domains' => [
        'identity_access' => 'Identity and Access',
        'diocesan_structure' => 'Diocesan Structure',
        'schools' => 'Schools',
        'reporting' => 'Reporting',
        'enrolment' => 'Enrolment',
        'attendance_welfare' => 'Attendance and Welfare',
        'staff' => 'Staff',
        'academics' => 'Academics',
        'infrastructure' => 'Infrastructure',
        'pastoral_education' => 'Pastoral Education',
        'governance_compliance' => 'Governance and Compliance',
        'inspection' => 'Inspection',
        'improvement_planning' => 'Improvement Planning',
        'development_projects' => 'Development Projects',
        'financial_statistics' => 'Financial Statistics',
        'documents' => 'Documents',
        'communications' => 'Communications',
        'analytics' => 'Analytics',
        'data_quality_audit' => 'Data Quality and Audit',
    ],

    'backup_restore_verified_at' => env('MDSISS_BACKUP_RESTORE_VERIFIED_AT'),
    'backup_restore_evidence' => env('MDSISS_BACKUP_RESTORE_EVIDENCE'),

    'storage' => [
        'protected_documents_disk' => env('MDSISS_PROTECTED_DOCUMENTS_DISK', 'local'),
    ],
];
