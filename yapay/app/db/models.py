from sqlalchemy import Column, Integer, String, Boolean, Enum, ForeignKey
from sqlalchemy.orm import relationship
from app.db.base import Base
import enum

class UserRole(enum.Enum):
    admin = "admin"
    teacher = "teacher"
    parent = "parent"
    student = "student"

class User(Base):
    __tablename__ = "users"

    id = Column(Integer, primary_key=True, index=True)
    email = Column(String, unique=True, index=True, nullable=False)
    username = Column(String, nullable=False)
    hashed_password = Column(String, nullable=False)  # ✅ Değiştirildi
    role = Column(Enum(UserRole), nullable=False)
    is_approved = Column(Boolean, default=False)

    # ✅ Öğretmene özel alanlar
    institution = Column(String, nullable=True)
    id_card_png_path = Column(String, nullable=True)

    # Öğrenci profili ile ilişki (sadece öğrenci için kullanılır)
    student_profile = relationship("StudentProfile", back_populates="user", uselist=False)


class StudentProfile(Base):
    __tablename__ = "student_profiles"

    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, ForeignKey("users.id"))
    first_name = Column(String, nullable=False)
    last_name = Column(String, nullable=False)
    age = Column(Integer, nullable=False)
    diagnosis = Column(String, nullable=False)  # Tanı

    user = relationship("User", back_populates="student_profile")
