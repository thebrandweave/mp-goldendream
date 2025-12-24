-- 1. Admins Table (Parent Table)
CREATE TABLE Admins (
    AdminID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255) ,
    Email VARCHAR(255)  ,
    PasswordHash VARCHAR(255) ,
    Role ENUM('SuperAdmin', 'Verifier') DEFAULT 'Verifier',
    Status ENUM('Active', 'Inactive') DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Schemes Table (Stores types of schemes)
CREATE TABLE Schemes (
    SchemeID INT AUTO_INCREMENT PRIMARY KEY,
    SchemeName VARCHAR(255)  ,
    SchemeImageURL VARCHAR(255)  ,
    Description TEXT,
    MonthlyPayment DECIMAL(10,2) ,
    TotalPayments INT  DEFAULT 1,
    StartDate DATE,
    Status ENUM('Active', 'Inactive') DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE Installments (
    InstallmentID INT AUTO_INCREMENT PRIMARY KEY,
    SchemeID INT,
    InstallmentName VARCHAR(100),
    InstallmentNumber INT,
    Amount DECIMAL(10,2),
    DrawDate DATE,
    Benefits TEXT,
    ImageURL VARCHAR(255),
    IsReplayable BOOLEAN DEFAULT FALSE,
    ReplaymentPercentage DECIMAL(5,2) DEFAULT 0.00,
    Status ENUM('Active', 'Inactive') DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (SchemeID) REFERENCES Schemes(SchemeID) ON DELETE CASCADE
);


-- 3. Promoters Table (Parent Table)
CREATE TABLE Promoters (
    PromoterID INT AUTO_INCREMENT PRIMARY KEY,
    PromoterUniqueID VARCHAR(50) UNIQUE ,
    CustomerID INT ,
    Name VARCHAR(255) ,
    Contact VARCHAR(50)  ,
    Email VARCHAR(255) ,
    PasswordHash VARCHAR(255) DEFAULT "$2y$10$f8RpDnV887jmqZKOTEm/oesy7nKRboD8HxH5yQMF0xdLO0aTGLnZm",
    Address TEXT,
    ProfileImageURL VARCHAR(255),
    BankAccountName VARCHAR(255),
    BankAccountNumber VARCHAR(50),
    IFSCCode VARCHAR(20),
    BankName VARCHAR(255),
    PaymentCodeCounter INT DEFAULT 0,
    ParentPromoterID  VARCHAR(50) DEFAULT NULL,
    TeamName VARCHAR(200),
    Status ENUM('Active', 'Inactive') DEFAULT 'Active',
    Commission  VARCHAR(200),
    ParentCommission  VARCHAR(200),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. Customers Table (Child of Promoters)
CREATE TABLE Customers (
    CustomerID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerUniqueID  VARCHAR(50)  ,
    Name VARCHAR(255) ,
    Contact VARCHAR(50)  ,
    Email VARCHAR(255) ,
    PasswordHash VARCHAR(255) DEFAULT "$2y$10$f8RpDnV887jmqZKOTEm/oesy7nKRboD8HxH5yQMF0xdLO0aTGLnZm" ,
    Address TEXT,
    ProfileImageURL VARCHAR(255),
    Gender ENUM('Male', 'Female', 'Other') DEFAULT NULL,
    DateOfBirth DATE DEFAULT NULL,

    BankAccountName VARCHAR(255),
    BankAccountNumber VARCHAR(50),
    IFSCCode VARCHAR(20),
    BankName VARCHAR(255),
    PromoterID VARCHAR(50),
    ReferredBy  VARCHAR(50), 
    TeamName VARCHAR(200),
    JoinedDate VARCHAR(50),
    Status ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);

-- 5. Payments Table (Child of Customers and Promoters)
CREATE TABLE Payments (
    PaymentID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT,
    PromoterID INT,
    AdminID INT DEFAULT NULL,
    SchemeID INT,
    InstallmentID INT,

    Amount DECIMAL(10,2) ,
    PaymentCodeValue INT DEFAULT 0,
    ScreenshotURL VARCHAR(255) ,
    Status ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
    SubmittedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    VerifiedAt TIMESTAMP NULL,
    PayerRemark VARCHAR(255), 
    VerifierRemark VARCHAR(255),
    FOREIGN KEY (CustomerID) REFERENCES Customers(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (PromoterID) REFERENCES Promoters(PromoterID) ON DELETE CASCADE,
    FOREIGN KEY (AdminID) REFERENCES Admins(AdminID) ON DELETE SET NULL,
    FOREIGN KEY (SchemeID) REFERENCES Schemes(SchemeID) ON DELETE SET NULL,
        FOREIGN KEY (InstallmentID) REFERENCES Installments(InstallmentID) ON DELETE SET NULL

);


-- 6. Payment Code Transactions (Child of Payments)
CREATE TABLE PaymentCodeTransactions (
    TransactionID INT AUTO_INCREMENT PRIMARY KEY,
    PromoterID INT,
    AdminID INT DEFAULT NULL,
    PaymentCodeChange INT ,
    TransactionType ENUM('Addition', 'Correction', 'Deduction') DEFAULT 'Addition',
    Remarks TEXT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (PromoterID) REFERENCES Promoters(PromoterID) ON DELETE CASCADE,
    FOREIGN KEY (AdminID) REFERENCES Admins(AdminID) ON DELETE SET NULL
);

-- 7. Payment Codes Per Month Table (For Promoters)
CREATE TABLE PaymentCodesPerMonth (
    RecordID INT AUTO_INCREMENT PRIMARY KEY,
    PromoterID INT,
    MonthYear DATE ,
    PaymentCodes INT DEFAULT 0,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (PromoterID) REFERENCES Promoters(PromoterID) ON DELETE CASCADE
);

-- 8. Notifications Table (Linked to all users)
CREATE TABLE Notifications (
    NotificationID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT ,
    UserType ENUM('Customer', 'Promoter', 'Admin') ,
    Message TEXT ,
    IsRead BOOLEAN DEFAULT FALSE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. Activity Logs Table (Logs actions from Admins and Promoters)
CREATE TABLE ActivityLogs (
    LogID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT ,
    UserType ENUM('Admin', 'Promoter') ,
    Action TEXT ,
    IPAddress VARCHAR(50),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 11. Subscriptions Table (Tracks customer subscriptions)
CREATE TABLE Subscriptions (
    SubscriptionID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT ,
    SchemeID INT ,
    StartDate DATE ,
    EndDate DATE ,
    RenewalStatus ENUM('Active', 'Expired', 'Cancelled') DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (CustomerID) REFERENCES Customers(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (SchemeID) REFERENCES Schemes(SchemeID) ON DELETE CASCADE
);

-- 10. Payment QR Table (Stores payment details for customers)
CREATE TABLE PaymentQR (
    QRID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT,
    UPIQRImageURL VARCHAR(255) ,
    BankAccountName VARCHAR(255) ,
    BankAccountNumber VARCHAR(50) ,
    IFSCCode VARCHAR(20) ,
    BankName VARCHAR(255) ,
    BankBranch VARCHAR(255) ,
    BankAddress TEXT ,
    RazorpayKeyID VARCHAR(100),
    RazorpayKeySecret VARCHAR(100),
    RazorpayContactID VARCHAR(100),
    RazorpayFundAccountID VARCHAR(100),
    RazorpayQRID VARCHAR(100),
    RazorpayQRStatus VARCHAR(50),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CustomerID) REFERENCES Customers(CustomerID) ON DELETE CASCADE
);

CREATE TABLE WhatsAppAPIConfig (
    ConfigID INT AUTO_INCREMENT PRIMARY KEY,
    APIProviderName VARCHAR(100) ,
    APIEndpoint VARCHAR(255) ,
    AccessToken TEXT ,
    Token VARCHAR(255) ,
    InstanceID VARCHAR(100) ,
    Status VARCHAR(20) DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE Withdrawals (
    WithdrawalID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT,
    UserType ENUM('Customer', 'Promoter') ,
    Amount DECIMAL(10,2) ,
    Status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    RequestedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ProcessedAt TIMESTAMP NULL,
    AdminID INT DEFAULT NULL,
    Remarks TEXT
);

CREATE TABLE Winners (
    WinnerID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT ,
    UserType ENUM('Customer', 'Promoter') ,
    PrizeType ENUM('Surprise Prize', 'Bumper Prize', 'Gift Hamper', 'Education Scholarship', 'Other') ,
    WinningDate TIMESTAMP,
    Status ENUM('Pending', 'Claimed', 'Expired') DEFAULT 'Pending',
    AdminID INT DEFAULT NULL,
    SchemeID INT DEFAULT NULL,
    InstallmentID INT DEFAULT NULL,
    DeliveryAddress TEXT,
    PreferredDeliveryDate DATE,
    Remarks TEXT,
    VerifiedAt DATE,
    FOREIGN KEY (AdminID) REFERENCES Admins(AdminID) ON DELETE SET NULL
);


CREATE TABLE Teams (
    TeamID INT AUTO_INCREMENT PRIMARY KEY,
    TeamUniqueID VARCHAR(50)  ,
    TeamName VARCHAR(255)  ,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 12. KYC Table (Stores KYC details for Customers and Promoters)
CREATE TABLE KYC (
    KYCID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT ,
    UserType ENUM('Customer', 'Promoter') ,
    AadharNumber VARCHAR(20)  ,
    PANNumber VARCHAR(10) ,
    IDProofType ENUM('Aadhar', 'PAN', 'Voter ID', 'Passport', 'Driving License') ,
    IDProofImageURL VARCHAR(255) ,
    AddressProofType ENUM('Aadhar', 'Voter ID', 'Utility Bill', 'Bank Statement', 'Ration Card') ,
    AddressProofImageURL VARCHAR(255) ,
    Status ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
    SubmittedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    VerifiedAt TIMESTAMP NULL,
    AdminID INT DEFAULT NULL,
    Remarks TEXT,
    KYCStatus VARCHAR(50),
    FOREIGN KEY (UserID) REFERENCES Customers(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (AdminID) REFERENCES Admins(AdminID) ON DELETE SET NULL
);

CREATE TABLE Balances (
    BalanceID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT,
    UserType ENUM('Customer', 'Promoter'),
    SchemeID INT,
    BalanceAmount DECIMAL(10,2) DEFAULT 0.00,
    Message VARCHAR(255), -- ✅ Added message column
    LastUpdated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES Customers(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (SchemeID) REFERENCES Schemes(SchemeID) ON DELETE CASCADE
);
CREATE TABLE PromoterWallet (
    BalanceID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT,
    PromoterUniqueID VARCHAR(50) UNIQUE ,
    BalanceAmount DECIMAL(10,2) DEFAULT 0.00,
    Message VARCHAR(255), -- ✅ Added message column (like: earned on first payment)
    LastUpdated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE WalletLogs (
    LogID INT AUTO_INCREMENT PRIMARY KEY,
    PromoterUniqueID VARCHAR(50),
    Amount DECIMAL(10,2),
    Message VARCHAR(255),
    TransactionType ENUM('Credit', 'Debit') ,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_url VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
