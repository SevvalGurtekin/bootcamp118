from fastapi import APIRouter, Request, Form, Depends
from sqlalchemy.orm import Session
from fastapi.templating import Jinja2Templates
from fastapi.responses import RedirectResponse
from app.db.session import SessionLocal
from app.db import models
import os

router = APIRouter()
templates = Jinja2Templates(directory=os.path.join(os.path.dirname(__file__), "../templates"))

# Veritabanı bağlantısı
def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

# ------------------------------------------
# Öğretmen Dashboard
# ------------------------------------------
@router.get("/teacher/dashboard")
def teacher_dashboard(request: Request):
    return templates.TemplateResponse("teacher_dashboard.html", {"request": request})

# ------------------------------------------
# Öğrenci Ekleme Formu (GET)
# ------------------------------------------
@router.get("/teacher/student/add")
def add_student_form(request: Request):
    return templates.TemplateResponse("add_student.html", {
        "request": request,
        "error": None,
        "message": None
    })

# ------------------------------------------
# Öğrenci Ekleme (POST)
# ------------------------------------------
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

# ------------------------------------------
# Öğrenci Listesi
# ------------------------------------------
@router.get("/teacher/students")
def list_students(request: Request, db: Session = Depends(get_db)):
    students = db.query(models.StudentProfile).all()
    return templates.TemplateResponse("student_list.html", {
        "request": request,
        "students": students
    })

# ------------------------------------------
# Öğrenci Düzenleme Formu (GET)
# ------------------------------------------
@router.get("/teacher/student/edit/{student_id}")
def edit_student_form(student_id: int, request: Request, db: Session = Depends(get_db)):
    student = db.query(models.StudentProfile).filter(models.StudentProfile.id == student_id).first()
    if not student:
        return templates.TemplateResponse("student_list.html", {
            "request": request,
            "students": db.query(models.StudentProfile).all(),
            "error": "Öğrenci bulunamadı."
        })
    return templates.TemplateResponse("edit_student.html", {
        "request": request,
        "student": student
    })

# ------------------------------------------
# Öğrenci Bilgisi Güncelleme (POST)
# ------------------------------------------
@router.post("/teacher/student/edit/{student_id}")
def update_student(
    student_id: int,
    request: Request,
    first_name: str = Form(...),
    last_name: str = Form(...),
    age: int = Form(...),
    diagnosis: str = Form(...),
    db: Session = Depends(get_db)
):
    student = db.query(models.StudentProfile).filter(models.StudentProfile.id == student_id).first()
    if not student:
        return templates.TemplateResponse("student_list.html", {
            "request": request,
            "students": db.query(models.StudentProfile).all(),
            "error": "Öğrenci bulunamadı."
        })

    student.first_name = first_name
    student.last_name = last_name
    student.age = age
    student.diagnosis = diagnosis
    db.commit()

    return RedirectResponse("/teacher/students", status_code=302)
# ------------------------------------------
# Öğrenci Silme
# ------------------------------------------
@router.get("/teacher/student/delete/{student_id}")
def delete_student(student_id: int, db: Session = Depends(get_db)):
    student = db.query(models.StudentProfile).filter(models.StudentProfile.id == student_id).first()
    if student:
        db.delete(student)
        db.commit()
    return RedirectResponse("/teacher/students", status_code=302)
