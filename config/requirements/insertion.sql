INSERT INTO Admins (Name, Email, PasswordHash, Role, Status)  
VALUES ('ajju', 'ajju@example.com', 'd74ff0ee8da3b9806b18c877dbf29bbde50b5bd8e4dad7a3a725000feb82e8f1', 'SuperAdmin', 'Active');
-- Azl@n2002
UPDATE Promoters
SET ParentPromoterID = NULL
WHERE ParentPromoterID IS NOT NULL
  AND ParentPromoterID NOT IN (
    SELECT PromoterUniqueID FROM (
      SELECT PromoterUniqueID FROM Promoters
    ) AS sub
  );


UPDATE Promoters
SET Commission = '750'
WHERE ParentPromoterID IS NULL;
