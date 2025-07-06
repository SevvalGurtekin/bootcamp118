from pydantic import BaseModel, EmailStr
from enum import Enum

class Role(str, Enum):
    teacher = "teacher"
    parent = "parent"
    student = "student"

class UserRegister(BaseModel):
    email: EmailStr
    username: str
    password: str
    role: Role  # Sadece "teacher" kayıt olacak

class UserLogin(BaseModel):
    email: EmailStr
    password: str
