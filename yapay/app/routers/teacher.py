from fastapi import APIRouter, Request, Form, Depends
from sqlalchemy.orm import Session
from fastapi.templating import Jinja2Templates
from app.db.session import SessionLocal
from app.db import models
import os

router = APIRouter()
templates = Jinja2Templates(directory=os.path.join(os.path.dirname(__file__), "../templates"))

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

# Öğretmen paneli ana sayfası
@router.get("/teacher/dashboard")
def teacher_dashboard(request: Request):
    return templates.TemplateResponse("teacher_dashboard.html", {"request": request})

# GET: Öğrenci ekleme formu
@router.get("/teacher/student/add")
def add_student_form(request: Request):
    return templates.TemplateResponse("add_student.html", {"request": request, "error": None, "message": None})

# POST: Öğrenci verisini kaydet
@router.post("/teacher/student/add")
def add_student(
    request: Request,
    first_name: str = Form(...),
    last_name: str = Form(...),
    age: int = Form(...),
    diagnosis: str = Form(...),
    db: Session = Depends(get_db)
):
    student = models.StudentProfile(
        first_name=first_name,
        last_name=last_name,
        age=age,
        diagnosis=diagnosis
    )
    db.add(student)
    db.commit()
    return templates.TemplateResponse("add_student.html", {
        "request": request,
        "message": "Öğrenci başarıyla eklendi.",
        "error": None
    })
